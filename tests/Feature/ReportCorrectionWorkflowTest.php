<?php

namespace Tests\Feature;

use App\Models\{Ipcr, Office, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCorrectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_correct_and_resubmit_a_report_returned_by_their_supervisor(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $supervisor = User::factory()->create(['office_id' => $office->id]);
        $supervisor->assignRole('Supervisor');
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee, 'prepared_by_user' => $employee]);

        $this->actingAs($employee)->get(route('ipcrs.show', $ipcr))->assertOk()->assertSee('Submit for review');
        $this->actingAs($employee)->patch(route('report-workflow.submit', ['ipcr', $ipcr]))->assertRedirect();
        $this->assertSame(Ipcr::STATUS_SUBMITTED, $ipcr->fresh()->status);

        $this->actingAs($supervisor)->patch(route('report-workflow.return', ['ipcr', $ipcr]), [
            'remarks' => 'Attach the missing accomplishment details.',
        ])->assertRedirect();
        $this->assertSame(Ipcr::STATUS_RETURNED, $ipcr->fresh()->status);

        $this->actingAs($employee)->put(route('report-workflow.update', ['ipcr', $ipcr]), [
            'title' => 'Corrected IPCR', 'success_indicators' => 'Complete', 'targets' => '100%',
        ])->assertRedirect();
        $this->actingAs($employee)->patch(route('report-workflow.submit', ['ipcr', $ipcr]))->assertRedirect();

        $ipcr->refresh();
        $this->assertSame(Ipcr::STATUS_RESUBMITTED, $ipcr->status);
        $this->assertSame(1, $ipcr->resubmission_count);
        $this->assertNotNull($ipcr->submitted_at);
        $this->assertCount(3, $ipcr->statusHistories);

        $this->actingAs($supervisor)->patch(route('report-workflow.approve', ['ipcr', $ipcr]))->assertRedirect();
        $this->assertSame(Ipcr::STATUS_APPROVED, $ipcr->fresh()->status);
        $this->assertCount(4, $ipcr->fresh()->statusHistories);
        $this->get(route('reports.index'))->assertOk()->assertSee('Corrected IPCR');
    }

    public function test_supervisor_cannot_review_another_offices_report(): void
    {
        $this->seed(RoleSeeder::class);
        $employee = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $employee->assignRole('Employee');
        $supervisor = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $supervisor->assignRole('Supervisor');
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee, 'prepared_by_user' => $employee, 'status' => Ipcr::STATUS_SUBMITTED]);

        $this->actingAs($supervisor)
            ->patch(route('report-workflow.return', ['ipcr', $ipcr]), ['remarks' => 'No access'])
            ->assertForbidden();
    }
}
