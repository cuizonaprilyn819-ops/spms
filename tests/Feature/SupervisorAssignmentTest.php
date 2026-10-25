<?php

namespace Tests\Feature;

use App\Models\{Office, User, Opcr, Dpcr, Upcr, Ipcr};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_assigns_and_manages_reports_for_office_employees_only(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();
        $supervisor = User::factory()->create(['office_id' => $office->id]);
        $supervisor->assignRole('Supervisor');
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $outsider = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $outsider->assignRole('Employee');
        $admin = User::factory()->create(['office_id' => $office->id]);
        $admin->assignRole('Administrator');
        $opcr = Opcr::factoryLike()->create(['office' => $office, 'status' => Opcr::STATUS_APPROVED]);
        $dpcr = Dpcr::factoryLike()->create(['opcr' => $opcr, 'employee' => $supervisor]);
        $upcr = Upcr::factoryLike()->create(['dpcr' => $dpcr, 'employee' => $supervisor]);
        $upcr->update(['status' => Upcr::STATUS_APPROVED]);

        foreach (['upcr' => ['dpcr_id' => $dpcr->id], 'ipcr' => ['upcr_id' => $upcr->id]] as $type => $parent) {
            $this->actingAs($supervisor)->get(route($type.'s.create'))
                ->assertOk()->assertSee($employee->name)->assertDontSee($outsider->name)->assertDontSee($admin->name);
            $data = $parent + ['employee_id' => $employee->id, 'title' => 'Assigned job report', 'targets' => 'Complete ten reviews'];
            $this->post(route($type.'s.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
            $class = $type === 'upcr' ? Upcr::class : Ipcr::class;
            $report = $class::where('employee_id', $employee->id)->sole();
            $this->assertEquals($supervisor->id, $report->prepared_by);
            $this->put(route('report-workflow.update', [$type, $report]), ['title' => 'Updated assignment', 'targets' => 'Complete twelve reviews'])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('Complete twelve reviews', $report->fresh()->targets);
            foreach ([$outsider, $admin] as $invalid) {
                $this->post(route($type.'s.store'), array_replace($data, ['employee_id' => $invalid->id]))->assertForbidden();
            }
            $this->actingAs($employee)->get(route($type.'s.index'))->assertOk()->assertSee('Updated assignment');
        }
    }
}
