<?php

namespace Tests\Feature;

use App\Models\{Ipcr, Office, Upcr, User};
use App\Notifications\ReportWorkflowNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusDirectorSubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesSupportedReportMov;

    public function test_director_receives_submissions_and_resubmissions_from_own_and_descendant_offices(): void
    {
        $this->seed(RoleSeeder::class);
        $root = Office::factory()->create(['campus' => 6, 'name' => 'Campus Director Office']);
        $division = Office::factory()->create(['campus' => 6, 'parent_office_id' => $root->id]);
        $unit = Office::factory()->create(['campus' => 6, 'parent_office_id' => $division->id, 'name' => 'CISA']);
        $director = User::factory()->create(['office_id' => $root->id])->assignRole('Campus Director');
        $unrelated = User::factory()->create(['office_id' => Office::factory()->create(['campus' => 6])->id])->assignRole('Campus Director');
        $upcr = Upcr::factoryLike()->create();
        $upcr->dpcr->opcr->evaluationCycle->update(['pcr_submission_due_at' => '2026-12-31 23:59:59']);
        foreach ([$root, $unit] as $office) {
            $employee = User::factory()->create(['office_id' => $office->id])->assignRole('Employee');
            $head = User::factory()->create(['office_id' => $office->id])->assignRole('Supervisor');
            $ipcr = Ipcr::factoryLike()->create(['employee' => $employee, 'prepared_by_user' => $employee, 'upcr' => $upcr]);
            $this->addSupportedReportMov($ipcr);
            $this->actingAs($employee)->patch(route('report-workflow.submit', ['ipcr', $ipcr]))->assertRedirect();
            $notice = $director->notifications()->where('data->report_id', $ipcr->id)->sole();
            $this->assertSame(ReportWorkflowNotification::class, $notice->type);
            $this->assertSame('IPCR report submitted', $notice->data['title']);
            $this->assertStringContainsString($employee->name, $notice->data['message']);
            $this->assertStringContainsString($office->name, $notice->data['message']);
            $this->assertSame($office->id, $notice->data['office_id']);
            $this->assertSame(1, $head->notifications()->count());
            $this->assertSame(0, $employee->notifications()->count());
            $this->actingAs($director)->get($notice->data['url'])->assertOk();
            $this->patchJson('/api/notifications/'.$notice->id.'/read')->assertOk();
            $apiReport = $this->getJson('/api/ipcrs/'.$ipcr->id)->assertOk()->assertJsonStructure(['html']);
            $this->assertTrue(mb_check_encoding($apiReport->json('html'), 'UTF-8'));
            $this->assertStringContainsString('Reviewer ratings', $apiReport->json('html'));
            $this->assertStringContainsString('÷', $apiReport->json('html'));
            $ipcr->update(['status' => 'returned']);
            $this->actingAs($employee)->patch(route('report-workflow.submit', ['ipcr', $ipcr]))->assertRedirect();
            $this->assertSame(2, $director->notifications()->where('data->report_id', $ipcr->id)->count());
            $this->assertSame(1, $director->notifications()->where('data->report_id', $ipcr->id)->where('data->title', 'IPCR report resubmitted')->count());
        }
        $this->assertSame(0, $unrelated->notifications()->count());
    }
}
