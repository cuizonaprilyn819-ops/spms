<?php

namespace Tests\Feature;

use App\Models\{Dpcr, EvaluationCycle, Ipcr, Office, OfficeTarget, Opcr, Upcr, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExplicitHeadRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_head_roles_offer_their_organizational_form_and_personal_ipcr(): void
    {
        $this->seed(RoleSeeder::class);
        foreach (['Unit Head' => 'upcr', 'Division Head' => 'dpcr'] as $role => $type) {
            $head = User::factory()->create(['office_id' => Office::factory()->create(['planning_level' => $role === 'Unit Head' ? 'division' : 'unit'])->id]);
            $head->assignRole($role);
            $this->assertSame($type, $head->organizationalReportType());
            $this->assertFalse($head->usesMyReports());
            $this->actingAs($head)->get(route('reports.index'))->assertOk()
                ->assertSee('Prepare '.strtoupper($type))->assertSee('Prepare IPCR')->assertDontSee('Prepare OPCR');
            $this->get(route('ipcrs.create'))->assertOk();
        }
    }

    public function test_explicit_heads_can_prepare_a_personal_ipcr_under_an_approved_office_upcr(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();
        $opcr = Opcr::factoryLike()->create(['office' => $office, 'status' => Opcr::STATUS_APPROVED]);
        $dpcr = Dpcr::factoryLike()->create(['opcr' => $opcr, 'status' => Dpcr::STATUS_APPROVED]);
        $upcr = Upcr::factoryLike()->create(['dpcr' => $dpcr, 'employee' => User::factory()->create(['office_id' => $office->id]), 'status' => Upcr::STATUS_APPROVED]);
        foreach (['Unit Head', 'Division Head'] as $role) {
            $head = User::factory()->create(['office_id' => $office->id]);
            $head->assignRole($role);
            $this->actingAs($head)->post(route('ipcrs.store'), [
                'upcr_id' => $upcr->id, 'employee_id' => $head->id, 'title' => $role.' personal IPCR',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('ipcrs', ['prepared_by' => $head->id, 'employee_id' => $head->id, 'upcr_id' => $upcr->id]);
        }
    }

    public function test_explicit_heads_create_only_their_correct_organizational_report(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();
        $opcr = Opcr::factoryLike()->create(['office' => $office, 'status' => Opcr::STATUS_APPROVED]);
        $parent = User::factory()->create(['office_id' => $office->id]);
        $dpcr = Dpcr::factoryLike()->create(['opcr' => $opcr, 'employee' => $parent, 'status' => Dpcr::STATUS_APPROVED]);
        foreach (['Unit Head' => 'upcr', 'Division Head' => 'dpcr'] as $role => $type) {
            $head = User::factory()->create(['office_id' => $office->id]);
            $head->assignRole($role);
            $wrong = $type === 'upcr' ? 'dpcr' : 'upcr';
            $this->actingAs($head)->get(route($wrong.'s.create'))->assertForbidden();
            $this->post(route($wrong.'s.store'), [])->assertForbidden();
            $this->get(route('opcrs.create'))->assertForbidden();
            $this->post(route('opcrs.store'), [])->assertForbidden();
            $this->get(route($type.'s.create'))->assertOk();
            $data = ['employee_id' => $head->id, 'title' => $role.' organizational report'];
            $data[$type === 'upcr' ? 'dpcr_id' : 'opcr_id'] = $type === 'upcr' ? $dpcr->id : $opcr->id;
            $this->post(route($type.'s.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas($type.'s', ['prepared_by' => $head->id, 'employee_id' => $head->id, 'title' => $data['title']]);
        }
    }

    public function test_explicit_heads_can_assign_only_their_own_office_employees(): void
    {
        $this->seed(RoleSeeder::class);
        foreach (['Unit Head', 'Division Head'] as $role) {
            $office = Office::factory()->create();
            $head = User::factory()->create(['office_id' => $office->id]);
            $head->assignRole($role);
            $employee = User::factory()->create(['office_id' => $office->id]);
            $employee->assignRole('Employee');
            $outsider = User::factory()->create();
            $outsider->assignRole('Employee');
            $this->assertEqualsCanonicalizing([$head->id, $employee->id], $head->reportAssignees()->pluck('id')->all());
            $target = OfficeTarget::create(['title' => 'Maintenance', 'success_indicator' => 'One activity',
                'target_value' => 1, 'unit_of_measure' => 'activity', 'weight' => 20,
                'deadline' => '2026-12-31', 'priority' => 'high', 'created_by' => $head->id]);
            $target->offices()->attach($office->id, ['allocated_target_value' => 1]);
            $this->actingAs($head)->post(route('office-targets.assign-employees', $target), [
                'employee_ids' => [$outsider->id], 'allocations' => [$outsider->id => 1],
            ])->assertForbidden();
            $this->post(route('office-targets.assign-employees', $target), [
                'employee_ids' => [$employee->id], 'allocations' => [$employee->id => 1],
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame([$employee->id], $target->assignedEmployees()->pluck('users.id')->all());
            $this->assertSame(1, $employee->notifications()->count());
            $this->assertSame(0, $outsider->notifications()->count());
        }
    }

    public function test_explicit_heads_cannot_view_or_edit_an_unrelated_office_ipcr(): void
    {
        $this->seed(RoleSeeder::class);
        $private = Ipcr::factoryLike()->create(['title' => 'Other office private report']);
        foreach (['Unit Head', 'Division Head'] as $role) {
            $head = User::factory()->create(['office_id' => Office::factory()->create()->id]);
            $head->assignRole($role);
            $this->actingAs($head)->get(route('ipcrs.show', $private))->assertForbidden();
            $this->get(route('report-workflow.edit', ['type' => 'ipcr', 'report' => $private->id]))->assertForbidden();
        }
    }

    public function test_role_migration_is_idempotent_and_preserves_legacy_supervisor_assignments(): void
    {
        $this->seed(RoleSeeder::class);
        $legacy = User::factory()->create();
        $legacy->assignRole('Supervisor');
        $migration = require database_path('migrations/2026_11_08_180000_add_explicit_office_head_roles.php');
        $migration->up();
        $migration->up();
        foreach (['Unit Head', 'Division Head'] as $role) {
            $this->assertSame(1, \Spatie\Permission\Models\Role::where('name', $role)->where('guard_name', 'web')->count());
        }
        $this->assertSame(['Supervisor'], $legacy->fresh()->getRoleNames()->all());
    }

    public function test_division_head_reminders_do_not_add_a_third_upcr_form_for_assigned_targets(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->activeForCampus()->create();
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('Division Head');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => '2nd Semester', 'status' => 'open',
            'pcr_creation_closes_at' => now()->addDay(), 'pcr_reminder_days' => 7]);
        $target = OfficeTarget::create(['title' => 'Division target', 'success_indicator' => 'One activity',
            'evaluation_cycle_id' => $cycle->id, 'target_value' => 1, 'unit_of_measure' => 'activity',
            'weight' => 20, 'deadline' => '2026-12-31', 'priority' => 'high', 'created_by' => $head->id]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 1]);
        $types = collect(app(\App\Services\PcrReminders::class)->pending($cycle))
            ->filter(fn ($item) => $item['user']->id === $head->id)->pluck('type')->all();
        $this->assertEqualsCanonicalizing(['dpcr', 'ipcr'], $types);
    }
}
