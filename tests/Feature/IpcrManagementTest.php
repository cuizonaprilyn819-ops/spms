<?php

namespace Tests\Feature;

use App\Models\Dpcr;
use App\Models\EvaluationCycle;
use App\Models\Ipcr;
use App\Models\Office;
use App\Models\Opcr;
use App\Models\Upcr;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_prepare_their_own_ipcr_even_when_another_employee_id_is_submitted(): void
    {
        $this->seed(RoleSeeder::class);

        $office = Office::factory()->activeForCampus()->create();
        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Employee');
        $assignedEmployee = User::factory()->create();
        $upcr = $this->approvedUpcrFor($office);

        $response = $this
            ->actingAs($user)
            ->post(route('ipcrs.store'), [
                'upcr_id' => $upcr->id,
                'employee_id' => $assignedEmployee->id,
                'title' => '2025 IPCR',
                'success_indicators' => 'Individual outputs completed.',
                'targets' => 'Complete assigned individual targets.',
            ]);

        $response->assertSessionHasNoErrors();
        $ipcr = Ipcr::sole();

        $response->assertRedirect(route('ipcrs.show', $ipcr));

        $this->assertDatabaseHas('ipcrs', [
            'upcr_id' => $upcr->id,
            'employee_id' => $user->id,
            'status' => Ipcr::STATUS_DRAFT,
            'prepared_by' => $user->id,
        ]);
        $this->assertDatabaseMissing('ipcrs', ['employee_id' => $assignedEmployee->id]);
    }

    public function test_employees_can_view_their_own_ipcr_but_not_another_employees_report(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create(['employee' => $user, 'prepared_by_user' => $user]);

        $response = $this
            ->actingAs($user)
            ->get(route('ipcrs.show', $ipcr));

        $response->assertOk();
        $response->assertSee($ipcr->title);
        $otherReport = Ipcr::factoryLike()->create(['upcr' => $ipcr->upcr, 'title' => 'Private unrelated IPCR']);
        $this->get(route('ipcrs.show', $otherReport))->assertForbidden();
        $this->get(route('ipcrs.index'))->assertOk()->assertSee($ipcr->title)->assertDontSee($otherReport->title);
    }

    public function test_supervisors_can_approve_ipcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $approver = User::factory()->create();
        $approver->assignRole('Supervisor');
        $employee = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $approver->update(['office_id' => $employee->office_id]);
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee, 'status' => Ipcr::STATUS_SUBMITTED]);

        $response = $this
            ->actingAs($approver)
            ->from(route('ipcrs.show', $ipcr))
            ->patch(route('report-workflow.approve', ['type' => 'ipcr', 'report' => $ipcr->id]));

        $response->assertRedirect(route('ipcrs.show', $ipcr));

        $ipcr->refresh();

        $this->assertSame(Ipcr::STATUS_APPROVED, $ipcr->status);
        $this->assertSame($approver->id, $ipcr->approved_by);
        $this->assertNotNull($ipcr->approved_at);
    }

    public function test_employees_cannot_approve_ipcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('report-workflow.approve', ['type' => 'ipcr', 'report' => $ipcr->id]));

        $response->assertForbidden();
        $this->assertSame(Ipcr::STATUS_DRAFT, $ipcr->fresh()->status);
    }

    public function test_ipcr_is_unique_per_employee_and_upcr(): void
    {
        $this->seed(RoleSeeder::class);

        $office = Office::factory()->activeForCampus()->create();
        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Employee');
        $upcr = $this->approvedUpcrFor($office);

        Ipcr::create([
            'upcr_id' => $upcr->id,
            'employee_id' => $user->id,
            'title' => 'Existing IPCR',
            'status' => Ipcr::STATUS_DRAFT,
            'prepared_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('ipcrs.store'), [
                'upcr_id' => $upcr->id,
                'employee_id' => $user->id,
                'title' => 'Duplicate IPCR',
            ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('ipcrs', 1);
        $this->assertDatabaseHas('ipcrs', ['employee_id' => $user->id, 'upcr_id' => $upcr->id, 'title' => 'Existing IPCR']);
    }

    public function test_employees_cannot_prepare_ipcrs_from_unapproved_or_other_office_upcrs(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->activeForCampus()->create();
        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Employee');
        $draft = $this->approvedUpcrFor($office);
        $draft->update(['status' => Upcr::STATUS_DRAFT]);
        $outside = $this->approvedUpcrFor(Office::factory()->activeForCampus()->create());
        foreach ([$draft, $outside] as $upcr) {
            $this->actingAs($user)->post(route('ipcrs.store'), [
                'upcr_id' => $upcr->id, 'employee_id' => $user->id, 'title' => 'Invalid parent commitment',
            ])->assertForbidden();
        }
        $this->assertDatabaseCount('ipcrs', 0);
    }

    private function approvedUpcrFor(Office $office): Upcr
    {
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('Supervisor');
        $cycle = EvaluationCycle::firstOrCreate([
            'year' => 2025, 'semester' => EvaluationCycle::FIRST_SEMESTER,
        ], ['status' => EvaluationCycle::STATUS_OPEN, 'opened_at' => now()]);
        $opcr = Opcr::factoryLike()->create(['office' => $office, 'evaluation_cycle' => $cycle, 'status' => Opcr::STATUS_APPROVED]);
        $dpcr = Dpcr::factoryLike()->create(['opcr' => $opcr, 'employee' => $head, 'status' => Dpcr::STATUS_APPROVED]);

        return Upcr::factoryLike()->create(['dpcr' => $dpcr, 'employee' => $head, 'status' => Upcr::STATUS_APPROVED]);
    }
}
