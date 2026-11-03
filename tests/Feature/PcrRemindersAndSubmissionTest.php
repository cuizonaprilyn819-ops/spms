<?php

namespace Tests\Feature;

use App\Models\{Dpcr, EvaluationCycle, Ipcr, Office, OfficeTarget, Opcr, PcrSubmission, Upcr, User};
use App\Notifications\PcrCreationReminder;
use App\Services\{PcrCompliance, PcrReminders};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Notification};
use Tests\TestCase;

class PcrRemindersAndSubmissionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesSupportedReportMov;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setupPeriod(): array
    {
        Carbon::setTestNow('2026-10-01 09:00:00');
        $this->seed(RoleSeeder::class);
        config(['mail.default' => 'smtp', 'spms.pcr.email_reminders' => true]);
        $office = Office::factory()->create(['name' => 'CISA', 'campus' => 6, 'is_active' => true]);
        $head = User::factory()->create(['office_id' => $office->id])->assignRole('Supervisor');
        $staff = User::factory()->create(['office_id' => $office->id])->assignRole('Employee');
        $office->update(['head_id' => $head->id]);
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open', 'pcr_creation_closes_at' => '2026-10-31 23:59:59', 'pcr_submission_due_at' => '2026-11-05 23:59:59', 'pcr_reminder_days' => 7]);
        $opcr = Opcr::create(['office_id' => $office->id, 'evaluation_cycle_id' => $cycle->id, 'title' => 'Office OPCR', 'prepared_by' => $head->id, 'status' => 'approved']);
        $dpcr = Dpcr::create(['opcr_id' => $opcr->id, 'employee_id' => $head->id, 'title' => 'Department DPCR', 'prepared_by' => $head->id, 'status' => 'approved']);
        $target = OfficeTarget::create(['title' => 'Maintenance', 'success_indicator' => 'One maintenance', 'target_value' => 1, 'weight' => 10, 'unit_of_measure' => 'maintenance', 'priority' => 'medium', 'deadline' => '2026-12-31', 'evaluation_cycle_id' => $cycle->id, 'created_by' => $head->id]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 1]);
        return compact('office', 'head', 'staff', 'cycle', 'dpcr', 'target');
    }

    public function test_missing_office_upcr_reminds_head_daily_on_both_channels_and_stops_after_creation(): void
    {
        extract($this->setupPeriod());
        Notification::fake();
        $service = app(PcrReminders::class);
        $first = $service->send();
        $this->assertEquals(1, $first['database']);
        $this->assertEquals(1, $first['mail']);
        Notification::assertSentTo($head, PcrCreationReminder::class, fn ($notice, $channels) => $notice->details['report_type'] === 'upcr' && in_array('mail', $channels));
        Notification::assertNotSentTo($staff, PcrCreationReminder::class);
        $again = $service->send();
        $this->assertEquals(0, $again['database'] + $again['mail']);
        Carbon::setTestNow('2026-10-02 09:00:00');
        $this->assertEquals(1, $service->send()['mail']);
        Upcr::create(['dpcr_id' => $dpcr->id, 'employee_id' => $head->id, 'title' => 'CISA UPCR', 'prepared_by' => $head->id, 'status' => 'draft']);
        Carbon::setTestNow('2026-10-03 09:00:00');
        $this->assertEquals(0, $service->send()['pending']);
        $this->assertDatabaseCount('pcr_reminder_deliveries', 4);
    }

    public function test_near_creation_closing_all_missing_personnel_are_reminded_but_existing_and_wrong_period_records_are_distinguished(): void
    {
        extract($this->setupPeriod());
        Notification::fake();
        Upcr::create(['dpcr_id' => $dpcr->id, 'employee_id' => $head->id, 'title' => 'CISA UPCR', 'prepared_by' => $head->id]);
        Ipcr::create(['employee_id' => $head->id, 'prepared_by' => $head->id, 'office_target_id' => $target->id, 'title' => 'Head IPCR']);
        $old = Ipcr::factoryLike()->create(['employee' => $staff, 'prepared_by_user' => $staff]);
        Carbon::setTestNow('2026-10-25 09:00:00');
        app(PcrReminders::class)->send();
        Notification::assertSentTo($staff, PcrCreationReminder::class, fn ($notice) => $notice->details['report_type'] === 'ipcr');
        Notification::assertNotSentTo($head, PcrCreationReminder::class);
        $this->assertTrue(app(PcrCompliance::class)->missing('ipcr', $staff, $cycle));
        $this->assertNotEquals($cycle->id, app(PcrCompliance::class)->cycle($old)->id);
    }

    public function test_submissions_are_reminded_until_first_submission_but_resubmissions_keep_original_deadline_and_time(): void
    {
        extract($this->setupPeriod());
        Notification::fake();
        $upcr = Upcr::create(['dpcr_id' => $dpcr->id, 'employee_id' => $head->id, 'title' => 'CISA UPCR', 'prepared_by' => $head->id, 'status' => 'approved']);
        $ipcr = Ipcr::create(['upcr_id' => $upcr->id, 'employee_id' => $staff->id, 'prepared_by' => $staff->id, 'title' => 'Staff IPCR', 'status' => 'draft']);
        $this->addSupportedReportMov($ipcr);
        Carbon::setTestNow('2026-11-03 12:00:00');
        app(PcrReminders::class)->send();
        Notification::assertSentTo($staff, PcrCreationReminder::class, fn ($notice) => str_contains($notice->details['title'], 'submission'));
        $this->actingAs($staff)->patch(route('report-workflow.submit', ['ipcr', $ipcr]))->assertRedirect();
        $record = $ipcr->submissionRecord()->sole();
        $this->assertFalse($record->isLate());
        $this->assertSame('2026-11-03 12:00:00', $record->first_submitted_at->toDateTimeString());
        $this->actingAs($head)->patch(route('report-workflow.return', ['ipcr', $ipcr]), ['remarks' => 'Correct a detail'])->assertRedirect();
        $cycle->update(['pcr_submission_due_at' => '2026-11-06 23:59:59']);
        Carbon::setTestNow('2026-11-08 12:00:00');
        $this->actingAs($staff)->patch(route('report-workflow.submit', ['ipcr', $ipcr]))->assertRedirect();
        $this->assertSame('2026-11-05 23:59:59', $record->fresh()->due_at->toDateTimeString());
        $this->assertSame('2026-11-03 12:00:00', $record->fresh()->first_submitted_at->toDateTimeString());
        $this->assertFalse(app(PcrCompliance::class)->missing('ipcr', $staff, $cycle, true));
        $this->assertSame('On time', collect(app(PcrCompliance::class)->summary($staff, $cycle))->firstWhere('type', 'IPCR')['status']);
    }

    public function test_late_submission_is_visible_in_ipcr_review_and_reviewer_timeliness_affects_average(): void
    {
        extract($this->setupPeriod());
        Notification::fake();
        $upcr = Upcr::create(['dpcr_id' => $dpcr->id, 'employee_id' => $head->id, 'title' => 'CISA UPCR', 'prepared_by' => $head->id, 'status' => 'approved']);
        $ipcr = Ipcr::create(['upcr_id' => $upcr->id, 'employee_id' => $staff->id, 'prepared_by' => $staff->id, 'title' => 'Late staff IPCR', 'status' => 'draft']);
        $this->addSupportedReportMov($ipcr);
        Carbon::setTestNow('2026-11-06 09:00:00');
        $this->actingAs($staff)->patch(route('report-workflow.submit', ['ipcr', $ipcr]))->assertRedirect();
        $this->assertTrue($ipcr->submissionRecord()->sole()->isLate());
        $this->actingAs($head)->get(route('ipcrs.show', $ipcr))->assertOk()->assertSee('Late')->assertSee('evidence for IPCR Timeliness');
        $this->patch(route('report-workflow.approve', ['ipcr', $ipcr]), ['quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 2, 'rating_remarks' => 'Late PCR submission considered.'])->assertSessionHasNoErrors();
        $this->assertEquals(4, $ipcr->fresh()->average_rating);
        $this->assertEquals(2, $ipcr->fresh()->timeliness_rating);
    }

    public function test_new_ipcr_creation_closes_but_existing_drafts_can_still_be_submitted_late(): void
    {
        extract($this->setupPeriod());
        Notification::fake();
        $target->assignedEmployees()->attach($staff->id, ['assigned_by' => $head->id, 'allocated_target_value' => 1, 'accepted_at' => now()]);
        Carbon::setTestNow('2026-11-06 09:00:00');
        $this->actingAs($staff)->post(route('ipcrs.store'), ['office_target_id' => $target->id, 'title' => 'New late IPCR', 'success_indicators' => 'One maintenance'])->assertSessionHasErrors('evaluation_cycle_id');
        $this->assertDatabaseCount('ipcrs', 0);
        $ipcr = Ipcr::create(['office_target_id' => $target->id, 'employee_id' => $staff->id, 'prepared_by' => $staff->id, 'title' => 'Existing draft', 'status' => 'draft']);
        $this->addSupportedReportMov($ipcr);
        $this->patch(route('report-workflow.submit', ['ipcr', $ipcr]))->assertRedirect();
        $this->assertTrue($ipcr->submissionRecord()->sole()->isLate());
    }

    public function test_planning_officer_controls_deadlines_and_restricted_roles_cannot_change_them(): void
    {
        extract($this->setupPeriod());
        $planner = User::factory()->create()->assignRole('Planning Officer');
        $data = ['pcr_creation_closes_at' => '2026-10-20', 'pcr_submission_due_at' => '2026-10-25', 'pcr_reminder_days' => 5];
        $this->actingAs($staff)->patch(route('evaluation-cycles.deadlines', $cycle), $data)->assertForbidden();
        $this->actingAs($planner)->patch(route('evaluation-cycles.deadlines', $cycle), $data)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 23:59:59', $cycle->fresh()->pcr_creation_closes_at->toDateTimeString());
        $this->get(route('evaluation-cycles.index'))->assertOk()->assertSee('PCR deadlines and reminders');
        $this->patch(route('evaluation-cycles.deadlines', $cycle), [...$data, 'pcr_submission_due_at' => '2026-10-19'])->assertSessionHasErrors('pcr_submission_due_at');
    }

    public function test_dry_run_and_log_mailer_do_not_claim_real_email_delivery(): void
    {
        $this->setupPeriod();
        Notification::fake();
        $this->artisan('spms:send-pcr-reminders', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('pcr_reminder_deliveries', 0);
        Notification::assertNothingSent();
        config(['mail.default' => 'log']);
        $result = app(PcrReminders::class)->send();
        $this->assertEquals(1, $result['database']);
        $this->assertEquals(0, $result['mail']);
    }

    public function test_failed_mail_is_retried_without_duplicating_successful_in_app_delivery(): void
    {
        $this->setupPeriod();
        $failMail = true;
        $databaseCalls = 0;
        $mailCalls = 0;
        Notification::shouldReceive('send')->andReturnUsing(function ($user, $notice) use (&$failMail, &$databaseCalls, &$mailCalls) {
            if ($notice->via($user) === ['mail']) {
                $mailCalls++;
                if ($failMail) throw new \RuntimeException('Synthetic SMTP failure');
            } else $databaseCalls++;
        });
        $first = app(PcrReminders::class)->send();
        $this->assertEquals(1, $first['database']);
        $this->assertEquals(1, $first['failed']);
        $this->assertEquals(0, $first['mail']);
        $failMail = false;
        $second = app(PcrReminders::class)->send();
        $this->assertEquals(1, $second['mail']);
        $this->assertEquals(0, $second['database']);
        $this->assertEquals(1, $databaseCalls);
        $this->assertEquals(2, $mailCalls);
        $this->assertEquals(2, DB::table('pcr_reminder_deliveries')->where('status', 'sent')->count());
    }

    public function test_closed_cycles_and_completed_pcrs_are_not_reminded(): void
    {
        extract($this->setupPeriod());
        Notification::fake();
        $cycle->update(['status' => 'closed']);
        $result = app(PcrReminders::class)->send();
        $this->assertEquals(0, $result['pending']);
        Notification::assertNothingSent();
    }

    public function test_all_four_pcr_types_record_first_submission_evidence(): void
    {
        extract($this->setupPeriod());
        Notification::fake();
        $opcr = $dpcr->opcr;
        $opcr->update(['status' => 'draft']);
        $dpcr->update(['status' => 'draft']);
        $upcr = Upcr::create(['dpcr_id' => $dpcr->id, 'employee_id' => $head->id, 'title' => 'UPCR', 'prepared_by' => $head->id, 'status' => 'draft']);
        $ipcr = Ipcr::create(['upcr_id' => $upcr->id, 'employee_id' => $staff->id, 'prepared_by' => $staff->id, 'title' => 'IPCR', 'status' => 'draft']);
        foreach (['opcr' => $opcr, 'dpcr' => $dpcr, 'upcr' => $upcr, 'ipcr' => $ipcr] as $type => $report) {
            $this->addSupportedReportMov($report);
            $this->actingAs($type === 'ipcr' ? $staff : $head)->patch(route('report-workflow.submit', [$type, $report]))->assertRedirect();
            $record = $report->submissionRecord()->sole();
            $this->assertEquals($cycle->id, $record->evaluation_cycle_id);
            $this->assertSame('2026-11-05 23:59:59', $record->due_at->toDateTimeString());
            $this->assertFalse($record->isLate());
        }
        $this->assertDatabaseCount('pcr_submissions', 4);
    }

    public function test_creation_closing_is_enforced_for_all_four_pcr_forms(): void
    {
        extract($this->setupPeriod());
        $cycle->update(['pcr_creation_closes_at' => '2026-09-30 23:59:59']);
        $directorOffice = Office::factory()->create(['campus' => 6, 'is_active' => true]);
        $director = User::factory()->create(['office_id' => $directorOffice->id])->assignRole('Campus Director');
        $this->actingAs($director)->post(route('opcrs.store'), ['office_id' => $directorOffice->id, 'evaluation_cycle_id' => $cycle->id, 'title' => 'Late OPCR'])->assertSessionHasErrors('evaluation_cycle_id');
        $this->actingAs($head)->post(route('dpcrs.store'), ['opcr_id' => $dpcr->opcr_id, 'employee_id' => $staff->id, 'title' => 'Late DPCR'])->assertSessionHasErrors('evaluation_cycle_id');
        $this->post(route('upcrs.store'), ['dpcr_id' => $dpcr->id, 'employee_id' => $head->id, 'title' => 'Late UPCR'])->assertSessionHasErrors('evaluation_cycle_id');
        $upcr = Upcr::create(['dpcr_id' => $dpcr->id, 'employee_id' => $head->id, 'title' => 'Existing UPCR', 'prepared_by' => $head->id, 'status' => 'approved']);
        $this->actingAs($staff)->post(route('ipcrs.store'), ['upcr_id' => $upcr->id, 'title' => 'Late IPCR'])->assertSessionHasErrors('evaluation_cycle_id');
        $this->assertDatabaseCount('opcrs', 1);
        $this->assertDatabaseCount('dpcrs', 1);
        $this->assertDatabaseCount('ipcrs', 0);
    }
}
