<?php

namespace Tests\Feature;

use App\Models\{Dpcr, Office, Opcr, Upcr, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementReportPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_roles_have_two_personal_forms_and_matching_reminder_requirements(): void
    {
        $this->seed(RoleSeeder::class);
        foreach (['Supervisor', 'HR', 'HR Head', 'Planning Officer'] as $role) {
            foreach (['division' => 'dpcr', 'unit' => 'upcr'] as $level => $type) {
                $office = Office::factory()->create(['planning_level' => $level]);
                $user = User::factory()->create(['office_id' => $office->id])->assignRole($role);
                $this->actingAs($user)->get(route('reports.index'))->assertOk()
                    ->assertViewHas('myReportTypes', fn ($types) => array_keys($types) === [$type, 'ipcr'])
                    ->assertSee('Prepare '.strtoupper($type))->assertSee('Prepare IPCR')->assertDontSee('Prepare OPCR');
                $this->assertSame(['ipcr', $type], app(\App\Services\PcrCompliance::class)->requiredReports($user));
                $this->get(route('opcrs.create'))->assertForbidden();
                $this->post(route('opcrs.store'), [])->assertForbidden();
            }
        }
    }

    public function test_management_roles_can_prepare_organizational_reports_within_their_reporting_path(): void
    {
        $this->seed(RoleSeeder::class);
        $cycle = \App\Models\EvaluationCycle::create(['year' => 2026, 'semester' => \App\Models\EvaluationCycle::FIRST_SEMESTER, 'status' => 'open']);
        foreach (['Supervisor', 'HR', 'HR Head', 'Planning Officer'] as $role) {
            $office = Office::factory()->create();
            $user = User::factory()->create(['office_id' => $office->id])->assignRole($role);
            $opcr = Opcr::factoryLike()->create(['office' => $office, 'evaluation_cycle' => $cycle, 'status' => Opcr::STATUS_APPROVED]);
            $this->actingAs($user)->get(route('dpcrs.create'))->assertOk();
            $this->post(route('dpcrs.store'), ['opcr_id' => $opcr->id, 'employee_id' => $user->id, 'title' => $role.' DPCR'])->assertSessionHasNoErrors()->assertRedirect();
            $dpcr = Dpcr::where('prepared_by', $user->id)->sole();
            $this->get(route('upcrs.create'))->assertOk();
            $this->post(route('upcrs.store'), ['dpcr_id' => $dpcr->id, 'employee_id' => $user->id, 'title' => $role.' UPCR'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($user->id, Upcr::where('dpcr_id', $dpcr->id)->sole()->prepared_by);
            $other = Opcr::factoryLike()->create(['evaluation_cycle' => $cycle, 'status' => Opcr::STATUS_APPROVED]);
            $this->post(route('dpcrs.store'), ['opcr_id' => $other->id, 'employee_id' => $user->id, 'title' => 'Outside office'])->assertForbidden();
        }
    }
}
