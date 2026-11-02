<?php

namespace Tests\Feature;

use App\Jobs\ScanTargetConflicts;
use App\Models\CommitmentPlan;
use App\Models\EvaluationCycle;
use App\Models\HistoricalPcr;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\Opcr;
use App\Models\PerformanceTarget;
use App\Models\TargetAssistance;
use App\Models\TargetConflictScan;
use App\Models\User;
use App\Notifications\TargetConflictNotification;
use App\Services\AiAssistant;
use App\Services\CommitmentPlanning;
use App\Services\TargetConflictReview;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TargetAssistanceReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    private Office $office;

    private CommitmentPlan $plan;

    private PerformanceTarget $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['spms.target_assistance.enabled' => true, 'spms.ai.enabled' => false]);
        $this->seed(RoleSeeder::class);
        Http::preventStrayRequests();
        $this->reviewer = User::factory()->create();
        $this->reviewer->assignRole('Administrator');
        $this->office = Office::factory()->create(['planning_level' => 'office', 'campus' => 6, 'mandate' => 'Arrange student exchange placements', 'hierarchy_verified_at' => now(), 'planning_reviewer_id' => $this->reviewer->id]);
        $owner = User::factory()->create(['office_id' => $this->office->id]);
        $owner->assignRole('Campus Director');
        $this->office->update(['head_id' => $owner->id]);
        $cycle = EvaluationCycle::create(['year' => 2027, 'semester' => '1st Semester', 'status' => 'open', 'planning_enabled' => true]);
        $this->plan = app(CommitmentPlanning::class)->createPlan($owner, $cycle, $this->office->fresh(), 'opcr', null);
        $this->target = app(CommitmentPlanning::class)->addTarget($this->plan, $owner, $this->payload());
    }

    private function payload(): array
    {
        return ['target' => '2 exchange students deployed abroad', 'success_indicator' => '2 students deployed with reviewed documents', 'measurement' => 'count', 'quantity' => '2', 'unit' => 'students', 'whole_numbers' => '1', 'standards' => 'Reviewed travel and deployment documents', 'deadline' => '2027-06-30', 'source_evidence' => 'Demo planning exercise only', 'weight' => '0'];
    }

    private function owner(): User
    {
        return User::findOrFail($this->plan->prepared_by);
    }

    private function rephrase(array $values = [], ?User $person = null)
    {
        return $this->actingAs($person ?? $this->owner())->postJson(route('target-assistance.preview'), ['action' => route('planning.target-update', $this->target), 'values' => $values ?: $this->payload(), 'field' => 'target']);
    }

    public function test_unsaved_wording_is_previewed_without_changing_any_commitment(): void
    {
        $this->mock(AiAssistant::class, fn ($m) => $m->shouldReceive('draft')->once()->with('rephrase', \Mockery::on(fn ($c) => $c['original'] === 'Deploy 2 exchange students abroad'))->andReturn('Arrange the overseas deployment of 2 exchange students'));
        $this->rephrase(['target' => 'Deploy 2 exchange students abroad'] + $this->payload())->assertOk()->assertSee('Arrange the overseas deployment of 2 exchange students');
        $this->assertSame('2 exchange students deployed abroad', $this->target->fresh()->target);
        $this->assertNull(TargetAssistance::sole()->accepted_at);
    }

    public function test_numeric_changes_are_rejected_and_ai_failure_keeps_manual_entry_available(): void
    {
        $this->mock(AiAssistant::class, fn ($m) => $m->shouldReceive('draft')->once()->andReturn('Deploy 4 exchange students abroad'));
        $this->rephrase()->assertStatus(503)->assertSee('Keep the original');
        $this->assertSame('failed', TargetAssistance::sole()->status);
    }

    public function test_outsiders_and_approved_targets_cannot_be_rephrased(): void
    {
        $outside = User::factory()->create();
        $outside->assignRole('Employee');
        $this->rephrase([], $outside)->assertForbidden();
        $this->plan->update(['state' => 'approved']);
        $this->rephrase()->assertForbidden();
    }

    public function test_accepted_suggestion_is_saved_and_audited_but_stale_suggestion_is_rejected(): void
    {
        $this->mock(AiAssistant::class, fn ($m) => $m->shouldReceive('draft')->twice()->andReturn('Deploy 2 exchange students abroad'));
        $this->rephrase()->assertOk();
        $a = TargetAssistance::sole();
        $this->actingAs($this->owner())->post(route('planning.target-update', $this->target), ['ai_assistance' => [$a->id], 'target' => $a->suggestion] + $this->payload())->assertRedirect();
        $this->assertSame('Deploy 2 exchange students abroad', $this->target->fresh()->target);
        $this->assertNotNull($a->fresh()->accepted_at);
        $this->assertDatabaseHas('workflow_audits', ['action' => 'AI wording accepted and saved']);
        $this->rephrase()->assertOk();
        $b = TargetAssistance::latest('id')->first();
        $this->target->update(['standards' => 'A reviewed revised standard']);
        $this->postJson(route('planning.target-update', $this->target), ['ai_assistance' => [$b->id], 'target' => $b->suggestion] + $this->payload())->assertStatus(422);
    }

    public function test_submission_publishes_ai_outage_and_requires_independent_manual_review(): void
    {
        app(CommitmentPlanning::class)->submit($this->plan, $this->owner());
        $scan = TargetConflictScan::sole();
        $this->assertTrue($scan->published);
        $this->assertSame('failed', $scan->status);
        $this->actingAs($this->owner())->post(route('target-conflicts.disposition', $scan), ['decision' => 'manual-review', 'reason' => 'I have checked my own records.'])->assertForbidden();
        $reviews = app(TargetConflictReview::class);
        try {
            $reviews->assertApproval($this->plan->fresh(), $this->reviewer);
            $this->fail('Approval should require manual review.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('manual conflict review', $e->getMessage());
        }
        $reviews->disposition($scan, $this->reviewer, 'manual-review', 'Compared the current quantities, responsibilities and deadlines manually.');
        $reviews->assertApproval($this->plan->fresh(), $this->reviewer);
    }

    public function test_ai_findings_notify_once_and_require_reviewer_disposition(): void
    {
        config(['spms.ai.enabled' => true]);
        Queue::fake();
        app(CommitmentPlanning::class)->submit($this->plan, $this->owner());
        $scan = TargetConflictScan::sole();
        $this->mock(AiAssistant::class, fn ($m) => $m->shouldReceive('draft')->once()->andReturn(json_encode(['findings' => [['code' => 'duty_mismatch', 'title' => 'Review exchange responsibility', 'explanation' => 'The recorded responsibility needs clarification.', 'recommendation' => 'Confirm the mandate with the head.', 'references' => ['target:'.$this->target->id]]]])));
        (new ScanTargetConflicts($scan->id))->handle(app(TargetConflictReview::class), app(AiAssistant::class));
        $scan->refresh();
        $this->assertSame('completed', $scan->status);
        $this->assertCount(1, $scan->findings);
        $count = DB::table('notifications')->where('type', TargetConflictNotification::class)->count();
        $this->assertSame(2, $count);
        app(TargetConflictReview::class)->notify($scan->fresh());
        $this->assertSame($count, DB::table('notifications')->where('type', TargetConflictNotification::class)->count());
        $this->actingAs($this->reviewer)->get(route('target-conflicts.show', $scan))->assertOk()->assertSee('Possible conflict');
        $this->post(route('target-conflicts.disposition', $scan), ['finding_id' => $scan->findings->first()->id, 'decision' => 'dismissed', 'reason' => 'The verified office mandate already covers this responsibility.'])->assertRedirect();
        app(TargetConflictReview::class)->assertApproval($this->plan->fresh(), $this->reviewer);
        Queue::assertPushed(ScanTargetConflicts::class);
    }

    public function test_ai_unknown_record_references_fail_without_claiming_a_clean_result(): void
    {
        config(['spms.ai.enabled' => true]);
        Queue::fake();
        $scan = app(TargetConflictReview::class)->scan($this->plan, $this->owner(), true, true);
        $this->mock(AiAssistant::class, fn ($m) => $m->shouldReceive('draft')->once()->andReturn(json_encode(['findings' => [['code' => 'possible_duplicate', 'title' => 'Wrong reference', 'explanation' => 'Untrusted unrelated reference.', 'recommendation' => 'Review it.', 'references' => ['target:999999']]]])));
        (new ScanTargetConflicts($scan->id))->handle(app(TargetConflictReview::class), app(AiAssistant::class));
        $this->assertSame('failed', $scan->fresh()->status);
        $this->assertCount(0, $scan->findings);
    }

    public function test_changed_targets_invalidate_scans_and_reviewer_decisions(): void
    {
        $reviews = app(TargetConflictReview::class);
        $scan = $reviews->scan($this->plan, $this->owner(), true, true);
        $reviews->disposition($scan, $this->reviewer, 'manual-review', 'Compared current records manually before approval.');
        $this->target->update(['quantity' => 3]);
        $this->assertFalse($reviews->current($scan));
        $this->actingAs($this->reviewer)->postJson(route('target-conflicts.disposition', $scan), ['decision' => 'manual-review', 'reason' => 'This result is no longer current.'])->assertStatus(422);
        $this->assertDatabaseCount('target_conflict_dispositions', 1);
    }

    public function test_private_checks_and_api_routes_do_not_leak_other_office_records(): void
    {
        $scan = app(TargetConflictReview::class)->scan($this->plan, $this->owner());
        $this->actingAs($this->reviewer)->get(route('target-conflicts.show', $scan))->assertForbidden();
        $other = User::factory()->create();
        $other->assignRole('Employee');
        $this->actingAs($other)->getJson('/api/target-conflicts/scans/'.$scan->id)->assertForbidden();
        $this->actingAs($this->owner())->getJson('/api/target-conflicts/scans/'.$scan->id)->assertOk()->assertJsonStructure(['html']);
    }

    public function test_rule_violations_cannot_be_dismissed_even_by_the_reviewer(): void
    {
        $this->target->update(['needs_reconciliation' => true]);
        $reviews = app(TargetConflictReview::class);
        $scan = $reviews->scan($this->plan, $this->owner(), false, true);
        $finding = $scan->findings->firstWhere('blocking', true);
        $this->assertNotNull($finding);
        $this->actingAs($this->reviewer)->postJson(route('target-conflicts.disposition', $scan), ['finding_id' => $finding->id, 'decision' => 'dismissed', 'reason' => 'Try to dismiss a verified rule violation.'])->assertStatus(422);
    }

    public function test_signed_historical_sources_only_allow_correction_and_note_assistance(): void
    {
        $owner = $this->owner();
        $pcr = HistoricalPcr::create(['evaluation_cycle_id' => $this->plan->evaluation_cycle_id, 'owner_id' => $owner->id, 'owner_name' => $owner->name, 'imported_by' => $this->reviewer->id, 'type' => 'ipcr', 'source_name' => 'signed-original.pdf', 'source_path' => 'private/signed-original.pdf', 'source_sha256' => str_repeat('a', 64), 'source_bytes' => 100, 'source_pages' => 1, 'form_pages' => 1, 'image_directory' => 'private/pages']);
        $revision = $pcr->revisions()->create(['number' => 1, 'snapshot' => ['notes' => [], 'rows' => [['indicator' => 'Original 2 students']], 'page_text' => ['Signed wording']], 'reason' => 'Original transcription', 'created_by' => $this->reviewer->id]);
        $pcr->update(['current_revision_id' => $revision->id]);
        $before = $revision->snapshot;
        $this->mock(AiAssistant::class, fn ($m) => $m->shouldReceive('draft')->once()->andReturn('Please verify the source wording on page 1.'));
        $this->actingAs($owner)->postJson(route('target-assistance.preview'), ['action' => route('historical-pcrs.correction', $pcr), 'field' => 'message', 'values' => ['message' => 'Check wording page 1.', 'revision_id' => (string) $revision->id]])->assertOk();
        $this->postJson(route('target-assistance.preview'), ['action' => route('historical-pcrs.update', $pcr), 'field' => 'snapshot.rows.0.indicator', 'values' => ['snapshot' => ['rows' => [['indicator' => 'Rewrite the original']]]]])->assertForbidden();
        $this->assertSame($before, $revision->fresh()->snapshot);
        $this->assertSame(str_repeat('a', 64), $pcr->fresh()->source_sha256);
    }

    public function test_legacy_report_approval_paths_require_review_without_reopening_approved_records(): void
    {
        $cycle = EvaluationCycle::create(['year' => 2028, 'semester' => '1st Semester', 'status' => 'open']);
        $report = Opcr::create(['office_id' => $this->office->id, 'evaluation_cycle_id' => $cycle->id, 'title' => 'Legacy office commitments', 'targets' => '2 placements', 'prepared_by' => $this->owner()->id, 'status' => 'submitted']);
        $this->actingAs($this->reviewer)->patchJson(route('report-workflow.approve', ['opcr', $report]))->assertStatus(422);
        $scan = app(TargetConflictReview::class)->scan($report, $this->reviewer, true, true);
        app(TargetConflictReview::class)->disposition($scan, $this->reviewer, 'manual-review', 'Compared legacy target wording and dates with the permitted records.');
        $this->patch(route('report-workflow.approve', ['opcr', $report]))->assertRedirect();
        $this->assertSame('approved', $report->fresh()->status);
    }

    public function test_legacy_assignment_checks_publish_and_do_not_duplicate_planning_projections(): void
    {
        $cycle = EvaluationCycle::create(['year' => 2028, 'semester' => '2nd Semester', 'status' => 'open']);
        $legacy = OfficeTarget::create(['evaluation_cycle_id' => $cycle->id, 'title' => '2 placements', 'success_indicator' => 'Deploy students', 'target_value' => 2, 'unit_of_measure' => 'students', 'weight' => 0, 'deadline' => '2028-12-31', 'created_by' => $this->owner()->id]);
        $legacy->offices()->attach($this->office->id, ['allocated_target_value' => 3]);
        $scan = app(TargetConflictReview::class)->scan($legacy, $this->owner(), true, true);
        $this->assertTrue($scan->findings->contains('blocking', true));
        $this->assertGreaterThan(0, $this->owner()->notifications()->where('type', TargetConflictNotification::class)->count());
        $projection = new OfficeTarget;
        $projection->forceFill(['evaluation_cycle_id' => $this->plan->evaluation_cycle_id, 'title' => 'Projection only', 'success_indicator' => 'Projection', 'target_value' => 2, 'unit_of_measure' => 'students', 'weight' => 0, 'deadline' => '2027-06-30', 'created_by' => $this->owner()->id, 'performance_target_id' => $this->target->id])->save();
        $context = app(TargetConflictReview::class)->context($this->plan, $this->reviewer);
        $this->assertCount(1, $context['snapshot']);
    }

    public function test_shared_cascade_is_not_reported_as_an_ai_duplicate(): void
    {
        config(['spms.ai.enabled' => true]);
        Queue::fake();
        $scan = app(TargetConflictReview::class)->scan($this->plan, $this->owner(), true, true);
        $this->mock(AiAssistant::class, fn ($m) => $m->shouldReceive('draft')->once()->andReturn(json_encode(['findings' => [['code' => 'possible_duplicate', 'title' => 'Same cascade', 'explanation' => 'Repeated linked wording.', 'recommendation' => 'Review the chain.', 'references' => ['target:'.$this->target->id]]]])));
        (new ScanTargetConflicts($scan->id))->handle(app(TargetConflictReview::class), app(AiAssistant::class));
        $this->assertSame('completed', $scan->fresh()->status);
        $this->assertCount(0, $scan->findings);
    }

    public function test_queue_failure_is_visible_and_never_a_clean_result(): void
    {
        $scan = app(TargetConflictReview::class)->scan($this->plan, $this->owner(), false, true);
        $scan->update(['status' => 'pending']);
        (new ScanTargetConflicts($scan->id))->failed(new \RuntimeException('Worker stopped'));
        $this->assertSame('failed', $scan->fresh()->status);
        $this->assertNotNull($scan->fresh()->failure);
    }

    public function test_abandoned_pending_check_can_be_retried_and_manually_reviewed(): void
    {
        $reviews = app(TargetConflictReview::class);
        $stuck = $reviews->scan($this->plan, $this->owner(), false, true);
        $stuck->update(['status' => 'pending', 'updated_at' => now()->subMinutes(11)]);
        $retry = $reviews->scan($this->plan, $this->owner(), true, true);
        $this->assertSame('failed', $stuck->fresh()->status);
        $this->assertNotSame($stuck->id, $retry->id);
        $this->assertSame('failed', $retry->status);
        $reviews->disposition($retry, $this->reviewer, 'manual-review', 'Checked current quantities and target relationships after the worker stopped.');
        $reviews->assertApproval($this->plan->fresh(), $this->reviewer);
    }

    public function test_api_rephrasing_uses_the_existing_rendered_page_contract(): void
    {
        $this->mock(AiAssistant::class, fn ($m) => $m->shouldReceive('draft')->once()->andReturn('Deploy 2 exchange students abroad'));
        $this->actingAs($this->owner())->postJson('/api/target-assistance/preview', ['action' => route('planning.target-update', $this->target), 'values' => $this->payload(), 'field' => 'target'])->assertOk()->assertJsonStructure(['html']);
    }
}
