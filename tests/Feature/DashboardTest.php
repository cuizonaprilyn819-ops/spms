<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Accomplishment;
use App\Models\Ipcr;
use App\Models\PerformanceTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_summary_uses_few_queries_and_keeps_totals_current(): void
    {
        Role::create(['name' => 'Administrator']);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        Office::factory()->create(['is_active' => true]);
        Office::factory()->create(['is_active' => false]);
        $this->actingAs($administrator);
        request()->setUserResolver(fn () => $administrator);
        $administrator->load('roles');
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $data = app(\App\Http\Controllers\DashboardController::class)()->getData();
        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queries);
        $this->assertSame(1, $data['counts']['Employees']);
        $this->assertSame(2, $data['counts']['Offices']);
        $this->assertSame(['Active' => 1, 'Inactive' => 1], $data['charts'][3]['values']);
        User::factory()->create();
        $this->assertSame(2, app(\App\Http\Controllers\DashboardController::class)()->getData()['counts']['Employees']);
    }

    public function test_employee_progress_query_count_does_not_grow_per_employee(): void
    {
        Role::create(['name' => 'Supervisor']);
        Role::create(['name' => 'Employee']);
        $office = Office::factory()->create();
        $supervisor = User::factory()->create(['office_id' => $office->id]);
        $supervisor->assignRole('Supervisor');
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $this->actingAs($supervisor);
        request()->setUserResolver(fn () => $supervisor);
        $supervisor->load(['office', 'roles']);
        $measure = function () {
            \Illuminate\Support\Facades\DB::enableQueryLog();
            \Illuminate\Support\Facades\DB::flushQueryLog();
            $data = app(\App\Http\Controllers\DashboardController::class)()->getData();
            $count = count(\Illuminate\Support\Facades\DB::getQueryLog());
            \Illuminate\Support\Facades\DB::disableQueryLog();
            return [$count, $data['personalDashboard']['employeeProgress']];
        };
        [$before] = $measure();
        $upcr = \App\Models\Upcr::factoryLike()->create();
        foreach (range(1, 5) as $index) {
            $employee = User::factory()->create(['office_id' => $office->id]);
            $employee->assignRole('Employee');
            $ipcr = Ipcr::factoryLike()->create(['employee' => $employee, 'upcr' => $upcr]);
            $target = PerformanceTarget::factoryLike()->create(['targetable' => $ipcr]);
            Accomplishment::factoryLike()->create([
                'performance_target' => $target, 'submitted_by_user' => $employee, 'completion_percentage' => 100,
            ]);
        }
        [$after, $progress] = $measure();
        $this->assertSame($before, $after);
        $this->assertCount(6, $progress);
        $this->assertSame(5, $progress->sum('completed'));
        $this->assertSame(5, $progress->sum('reportTargets'));
    }

    public function test_navigation_counts_unread_notifications_once_and_refreshes_next_request(): void
    {
        $user = User::factory()->create();
        $notification = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test', 'data' => ['title' => 'Test notice'],
        ]);
        $this->actingAs($user);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $this->get(route('dashboard'))->assertOk()->assertSee('1 unread');
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->assertCount(1, $queries->filter(fn ($query) => str_contains($query['query'], 'notifications') && str_contains($query['query'], 'count(*)')));
        $notification->markAsRead();
        $this->get(route('dashboard'))->assertOk()->assertSee('no unread notifications');
    }

    public function test_cross_campus_progress_only_includes_the_directors_own_assignments(): void
    {
        Role::create(['name' => 'Campus Director']);
        Role::create(['name' => 'Employee']);
        $director = User::factory()->create(['office_id' => Office::factory()->create(['campus' => 1])->id]);
        $director->assignRole('Campus Director');
        $employee = User::factory()->create(['office_id' => Office::factory()->create(['campus' => 2])->id]);
        $employee->assignRole('Employee');
        $otherDirector = User::factory()->create();
        foreach ([$director, $otherDirector] as $owner) {
            $target = \App\Models\OfficeTarget::create([
                'title' => 'Assigned work', 'success_indicator' => 'One output', 'target_value' => 1,
                'unit_of_measure' => 'report', 'weight' => 20, 'deadline' => '2026-12-31',
                'priority' => 'medium', 'created_by' => $owner->id,
            ]);
            $target->offices()->attach($employee->office_id, ['allocated_target_value' => 1]);
            $target->assignedEmployees()->attach($employee->id, ['assigned_by' => $owner->id, 'allocated_target_value' => 1, 'completed_at' => now()]);
            foreach (range(1, 2) as $index) {
                \App\Models\TargetMov::create([
                    'office_target_id' => $target->id, 'user_id' => $employee->id,
                    'office_id' => $employee->office_id, 'accomplishment' => 'Done',
                    'path' => 'test.pdf', 'original_name' => 'test.pdf',
                ]);
            }
        }
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee]);
        PerformanceTarget::factoryLike()->create(['targetable' => $ipcr]);
        $this->actingAs($director)->get(route('dashboard'))->assertOk()
            ->assertViewHas('personalDashboard', function ($data) use ($employee) {
                $row = $data['employeeProgress']->sole();
                return $row['name'] === $employee->name && $row['officeTargets'] === 1
                    && $row['reportTargets'] === 0 && $row['submitted'] === 1 && $row['completed'] === 1
                    && $data['submittedAccomplishments'] === 1 && $data['accomplishedTargets'] === 1;
            });
    }

    public function test_administrator_dashboard_displays_system_totals_and_charts(): void
    {
        Role::create(['name' => 'Administrator']);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        Office::factory()->create();

        $this->actingAs($administrator)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('All SPMS records at a glance')
            ->assertSee('System totals')
            ->assertSee('System distributions')
            ->assertSee('Performance records')
            ->assertSee('Employees by role');
    }

    public function test_non_administrator_keeps_the_standard_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee("Welcome, {$user->name}.")
            ->assertDontSee('System distributions');
    }

    public function test_each_personnel_role_can_open_its_dashboard(): void
    {
        foreach (['Campus Director', 'Supervisor', 'HR Head', 'HR', 'HRM Office II', 'Employee'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $this->actingAs($user)->get(route('dashboard'))
                ->assertOk()
                ->assertSee($roleName.' workspace')
                ->assertSee('Target Accomplishment Status');
        }
    }

    public function test_user_dashboard_shows_personal_target_and_accomplishment_totals(): void
    {
        $user = User::factory()->create();
        $ipcr = Ipcr::factoryLike()->create(['employee' => $user]);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $ipcr]);
        Accomplishment::factoryLike()->create([
            'performance_target' => $target,
            'submitted_by_user' => $user,
            'completion_percentage' => 100,
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Assigned Targets')
            ->assertSee('Total Accomplishments')
            ->assertSee('Accomplished Targets')
            ->assertSee('Target Accomplishment Status')
            ->assertSee('Completed');
    }

    public function test_campus_director_dashboard_shows_employee_progress_for_their_campus(): void
    {
        Role::create(['name' => 'Campus Director']);
        Role::create(['name' => 'Employee']);
        $directorOffice = Office::factory()->create(['campus' => 1]);
        $employeeOffice = Office::factory()->create(['campus' => 1, 'name' => 'Campus One Office']);
        $otherOffice = Office::factory()->create(['campus' => 2]);
        $director = User::factory()->create(['office_id' => $directorOffice->id]);
        $director->assignRole('Campus Director');
        $employee = User::factory()->create(['office_id' => $employeeOffice->id, 'name' => 'Visible Campus Employee']);
        $employee->assignRole('Employee');
        $otherEmployee = User::factory()->create(['office_id' => $otherOffice->id, 'name' => 'Other Campus Employee']);
        $otherEmployee->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee]);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $ipcr]);
        Accomplishment::factoryLike()->create([
            'performance_target' => $target,
            'submitted_by_user' => $employee,
            'completion_percentage' => 50,
        ]);

        $this->actingAs($director)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Employee Progress')
            ->assertSee('Visible Campus Employee')
            ->assertSee('50.0%')
            ->assertDontSee('Other Campus Employee');
    }
}
