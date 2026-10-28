<?php

namespace Tests\Feature;

use App\Models\CommitmentPlan;
use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\PerformanceTarget;
use App\Models\PlanningOutput;
use App\Models\TargetDocument;
use App\Models\TargetProposal;
use App\Models\User;
use App\Services\CommitmentPlanning;
use App\Services\PlanningOrganization;
use App\Services\TargetAllocation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommitmentPlanningTest extends TestCase
{
    use RefreshDatabase;

    private CommitmentPlanning $engine;

    private EvaluationCycle $cycle;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        $this->engine = app(CommitmentPlanning::class);
        $this->reviewer = User::factory()->create();
        $this->reviewer->assignRole('Administrator');
        $this->cycle = EvaluationCycle::create(['year' => 2027, 'semester' => '1st Semester', 'status' => 'open', 'planning_enabled' => true]);
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function office(string $level, ?Office $parent = null): Office
    {
        $o = Office::factory()->create(['planning_level' => $level, 'parent_office_id' => $parent?->id, 'campus' => 6, 'mandate' => 'Verified processing and support responsibilities', 'hierarchy_verified_at' => now(), 'planning_reviewer_id' => $this->reviewer->id]);
        $u = User::factory()->create(['office_id' => $o->id, 'planning_duties' => 'Manage and process assigned cases']);
        $u->assignRole($level === 'office' ? 'Campus Director' : 'Supervisor');
        $o->update(['head_id' => $u->id]);

        return $o->fresh();
    }

    private function plan(Office $office, string $level, ?CommitmentPlan $parent = null, ?User $employee = null, ?string $reason = null): CommitmentPlan
    {
        return $this->engine->createPlan($employee ?? $office->head, $this->cycle, $office, $level, $parent, $employee, $reason);
    }

    private function data(string $measure = 'count', float $quantity = 100): array
    {
        return ['target' => 'Process eligible requests', 'success_indicator' => 'Requests processed correctly and on time', 'measurement' => $measure, 'quantity' => $quantity, 'unit' => $measure === 'rate' ? '%' : 'requests', 'whole_numbers' => true, 'standards' => 'No errors, within two working days', 'deadline' => '2027-06-30', 'source_evidence' => 'Approved work plan, output 1', 'weight' => 25];
    }

    private function root(string $measure = 'count', float $quantity = 100): array
    {
        $office = $this->office('office');
        $plan = $this->plan($office, 'opcr');
        $line = $this->engine->addTarget($plan, $office->head, $this->data($measure, $quantity));
        $this->approve($plan);

        return [$office, $plan->fresh(), $line];
    }

    private function approve(CommitmentPlan $plan): void
    {
        $this->engine->submit($plan, User::findOrFail($plan->prepared_by));
        $plan->refresh();
        if ($plan->employee_id) {
            $plan->update(['agreed_at' => now()]);
        }
        $this->engine->approve($plan, $this->reviewer, ['pmt_reviewer' => 'External PMT', 'university_approver' => 'University approver', 'approved_on' => '2026-10-28', 'evidence' => 'approved.pdf']);
    }

    private function link(CommitmentPlan $parent, PerformanceTarget $source, Office $office, string $level, float $quantity, ?User $person = null): array
    {
        DB::table('target_eligibilities')->insert(['performance_target_id' => $source->id, 'office_id' => $office->id, 'employee_id' => $person?->id, 'reason' => 'Verified duty', 'confirmed_by' => $parent->prepared_by]);
        $plan = $this->plan($office, $level, $parent, $person);
        $line = $this->engine->addTarget($plan, User::findOrFail($plan->prepared_by), ['target' => $source->target, 'quantity' => $quantity, 'weight' => 10], $source);

        return [$plan, $line];
    }

    public function test_full_chain_reserves_shares_and_rolls_outputs_up_once(): void
    {
        [$office,$root,$source] = $this->root();
        $a = $this->office('division', $office);
        $b = $this->office('division', $office);
        [$pa,$ta] = $this->link($root, $source, $a, 'dpcr', 60);
        $this->approve($pa);
        [$pb,$tb] = $this->link($root, $source, $b, 'dpcr', 40);
        $this->approve($pb);
        foreach ([[$pa, $ta, $a, 60], [$pb, $tb, $b, 40]] as [$parent,$target,$division,$amount]) {
            $unit = $this->office('unit', $division);
            [$up,$ut] = $this->link($parent, $target, $unit, 'upcr', $amount);
            $this->approve($up);
            $employee = User::factory()->create(['office_id' => $unit->id, 'planning_duties' => 'Process assigned requests']);
            $employee->assignRole('Employee');
            [$ip,$it] = $this->link($up, $ut, $unit, 'ipcr', $amount, $employee);
            $this->approve($ip);
            $this->actingAs($employee)->post(route('planning.output', $it), ['reference' => 'BATCH-'.$unit->id, 'details' => 'New completed requests', 'quantity' => $amount, 'evidence' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
            $out = PlanningOutput::where('performance_target_id', $it->id)->sole();
            $this->actingAs($this->reviewer)->post(route('planning.output-validate', $out))->assertRedirect();
            $this->assertSame('draft', $ip->report->fresh()->status);
        }
        $summary = $this->engine->summary($source);
        $this->assertEquals(100, $summary['reported']);
        $this->assertEquals(100, $summary['validated']);
        $this->assertEquals(0, $summary['unallocated']);
        $this->assertDatabaseCount('planning_outputs', 2);
        $this->actingAs($office->head)->get(route('planning.show', $root))->assertOk()->assertSee('100')->assertSee('Validated');
        $this->getJson('/api/planning/'.$root->id)->assertOk()->assertJsonStructure(['html']);
        $this->get(route('planning.export', [$root, 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('planning.export', [$root, 'excel']))->assertOk()->assertStreamedContent(view('planning.export', ['plan' => $root, 'lines' => $root->targets()->get()->map(fn ($t) => ['target' => $t, 'summary' => $this->engine->summary($t)])])->render());
    }

    public function test_rate_aggregation_uses_underlying_counts_and_does_not_divide_standard(): void
    {
        [$office,$root,$source] = $this->root('rate', 95);
        foreach ([[90, 100], [10, 10]] as $i => [$num,$den]) {
            $division = $this->office('division', $office);
            [$plan,$target] = $this->link($root, $source, $division, 'dpcr', 50);
            $this->approve($plan);
            $this->assertEquals(95, $target->quantity);
            PlanningOutput::create(['performance_target_id' => $target->id, 'root_target_id' => $source->id, 'reference' => 'R'.$i, 'details' => 'Verified cases', 'quantity' => 1, 'numerator' => $num, 'denominator' => $den, 'evidence' => [], 'submitted_by' => $division->head_id, 'validated_at' => now(), 'validated_by' => $this->reviewer->id]);
        }
        $this->assertEquals(90.91, $this->engine->summary($source)['validated']);
    }

    public function test_missing_driver_never_produces_an_equal_split_and_rounding_respects_capacity(): void
    {
        $a = app(TargetAllocation::class);
        $this->assertSame(['shares' => [], 'unallocated' => 10.0], $a->suggest(10, []));
        $s = $a->suggest(10, [2 => ['value' => 1, 'office_id' => 2], 1 => ['value' => 1, 'office_id' => 1], 3 => ['value' => 1, 'office_id' => 3]]);
        $this->assertEquals([2 => 3, 1 => 4, 3 => 3], $s['shares']);
        $s = $a->suggest(100, [1 => ['value' => 60, 'capacity' => 50], 2 => ['value' => 40, 'capacity' => 30]]);
        $this->assertEquals([1 => 50, 2 => 30], $s['shares']);
        $this->assertEquals(20, $s['unallocated']);
    }

    public function test_approval_rechecks_remaining_allocation_and_ineligible_target_is_blocked(): void
    {
        [$office,$root,$source] = $this->root();
        $a = $this->office('division', $office);
        $b = $this->office('division', $office);
        [$pa,$ta] = $this->link($root, $source, $a, 'dpcr', 60);
        [$pb,$tb] = $this->link($root, $source, $b, 'dpcr', 60);
        $this->approve($pa);
        try {
            $this->approve($pb);
            $this->fail('Over-allocation was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('remaining', $e->getMessage());
        }
        $this->assertSame('submitted', $pb->fresh()->state);
        $this->assertEquals(40, $this->engine->summary($source)['unallocated']);
        DB::table('target_eligibilities')->where('office_id', $b->id)->delete();
        $this->actingAs($b->head)->get(route('planning.show', $pb))->assertOk()->assertDontSee('Add linked target to draft');
    }

    public function test_amendments_preserve_old_lines_and_mark_descendants_for_reconciliation(): void
    {
        [$office,$root,$source] = $this->root();
        $division = $this->office('division', $office);
        [$p,$t] = $this->link($root, $source, $division, 'dpcr', 60);
        $this->approve($p);
        $this->engine->amend($root, $office->head, 'New institutional requirement');
        $root->refresh();
        $this->assertEquals(2, $root->revision);
        $this->assertEquals(100, $source->fresh()->quantity);
        $this->assertTrue($t->fresh()->needs_reconciliation);
        $copy = $root->targets()->sole();
        $this->assertEquals($source->id, $copy->supersedes_id);
        $this->assertSame('draft', $root->report->fresh()->status);
        $this->assertDatabaseCount('performance_targets', 3);
    }

    public function test_head_ipcr_uses_actual_level_and_regular_employee_cannot_skip_units(): void
    {
        [$office,$root,$source] = $this->root();
        $headPlan = $this->engine->createPlan($office->head, $this->cycle, $office, 'ipcr', $root, $office->head, 'Personal leadership responsibilities');
        $this->assertNull($headPlan->report->upcr_id);
        $employee = User::factory()->create(['office_id' => $office->id, 'planning_duties' => 'Process requests']);
        $this->expectException(ValidationException::class);
        $this->engine->createPlan($employee, $this->cycle, $office, 'ipcr', $root, $employee);
    }

    public function test_external_approval_employee_agreement_and_no_self_approval_are_enforced(): void
    {
        $office = $this->office('office');
        $plan = $this->plan($office, 'opcr');
        $this->engine->addTarget($plan, $office->head, $this->data());
        $this->engine->submit($plan, $office->head);
        $plan->refresh();
        $this->actingAs($office->head)->post(route('planning.transition', [$plan, 'approve']))->assertForbidden();
        $this->actingAs($this->reviewer)->post(route('planning.transition', [$plan, 'approve']))->assertSessionHasErrors('planning');
        $this->assertSame('submitted', $plan->fresh()->state);
    }

    public function test_new_period_blocks_legacy_shortcuts_and_unrelated_offices(): void
    {
        [$office,$root,$source] = $this->root();
        $this->actingAs($office->head)->post(route('office-targets.store'), ['evaluation_cycle_id' => $this->cycle->id])->assertStatus(422);
        $other = $this->office('office');
        $this->actingAs($other->head)->get(route('planning.show', $root))->assertForbidden();
        $projection = OfficeTarget::where('performance_target_id', $source->id)->sole();
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $this->actingAs($employee)->post(route('ipcrs.store'), ['office_target_id' => $projection->id, 'title' => 'Bypass', 'success_indicators' => 'No'])->assertStatus(422);
        $this->assertDatabaseCount('ipcrs', 0);
    }

    public function test_outputs_are_not_double_counted_and_overachievement_keeps_original_commitment(): void
    {
        [$office,$root,$source] = $this->root('count', 10);
        $payload = ['reference' => 'UNIQUE-1', 'details' => 'Extra requests completed', 'quantity' => 12, 'evidence' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')];
        $this->actingAs($office->head)->post(route('planning.output', $source), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('planning.output', $source), $payload)->assertStatus(422);
        $this->assertDatabaseCount('planning_outputs', 1);
        $s = $this->engine->summary($source);
        $this->assertEquals(12, $s['reported']);
        $this->assertEquals(0, $s['validated']);
        $this->assertEquals(2, $s['extra']);
        $this->assertEquals(10, $source->fresh()->quantity);
    }

    public function test_proposals_forward_through_heads_without_mutating_approved_targets(): void
    {
        [$office,$root,$source] = $this->root();
        $division = $this->office('division', $office);
        $unit = $this->office('unit', $division);
        $employee = User::factory()->create(['office_id' => $unit->id]);
        $employee->assignRole('Employee');
        $this->actingAs($employee)->post(route('planning.proposal'), ['evaluation_cycle_id' => $this->cycle->id, ...$this->data('count', 5), 'target' => 'Additional outreach requests', 'source_evidence' => 'New work-plan responsibility'])->assertRedirect()->assertSessionHasNoErrors();
        $proposal = TargetProposal::sole();
        foreach ([$unit, $division] as $reviewOffice) {
            $this->actingAs($reviewOffice->head)->post(route('planning.proposal-review', $proposal), ['decision' => 'forward', 'review_notes' => 'Relevant to our work'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->actingAs($office->head)->post(route('planning.proposal-review', $proposal), ['decision' => 'materialize', 'root_plan_id' => $root->id, 'review_notes' => 'Add additional responsibility'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $proposal->fresh()->state);
        $this->assertSame('draft', $root->fresh()->state);
        $this->assertEquals(100, $source->fresh()->quantity);
    }

    public function test_hierarchy_cycles_and_missing_duties_are_rejected(): void
    {
        $o = $this->office('office');
        $d = $this->office('division', $o);
        $u = $this->office('unit', $d);
        $o->update(['parent_office_id' => $u->id]);
        $this->expectException(ValidationException::class);
        app(PlanningOrganization::class)->verify($o->fresh());
    }

    public function test_ai_failure_keeps_manual_planning_available_and_creates_nothing(): void
    {
        [$office,$root,$source] = $this->root();
        $d = $this->office('division', $office);
        [$p,$t] = $this->link($root, $source, $d, 'dpcr', 50);
        config(['spms.ai.enabled' => false]);
        $before = PerformanceTarget::count();
        $this->actingAs($d->head)->post(route('planning.suggest', $p))->assertRedirect()->assertSessionHasErrors('ai');
        $this->assertSame($before, PerformanceTarget::count());
    }

    public function test_cumulative_submissions_and_validation_do_not_add_repeated_totals(): void
    {
        [$office,$root,$target] = $this->root('count', 10);
        foreach ([['C1', 4], ['C2', 4], ['C3', 7]] as [$reference,$quantity]) {
            $this->actingAs($office->head)->post(route('planning.output', $target), ['reference' => $reference, 'quantity' => $quantity, 'submission_mode' => 'cumulative', 'details' => 'Cumulative requests', 'evidence' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertEquals(7, $this->engine->summary($target)['reported']);
        $out = PlanningOutput::where('reference', 'C2')->sole();
        $this->actingAs($this->reviewer)->post(route('planning.output-validate', $out))->assertRedirect();
        $this->assertEquals(4, $this->engine->summary($target)['validated']);
    }

    public function test_supplementary_evidence_never_adds_another_quantity(): void
    {
        [$office,$root,$target] = $this->root('count', 10);
        $this->actingAs($office->head)->post(route('planning.output', $target), ['reference' => 'EVIDENCE-1', 'details' => 'Ten requests', 'quantity' => 10, 'evidence' => UploadedFile::fake()->create('first.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $out = PlanningOutput::sole();
        $this->post(route('planning.output-evidence', $out), ['evidence' => UploadedFile::fake()->create('extra.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(10, $this->engine->summary($target)['reported']);
        $this->assertDatabaseCount('planning_outputs', 1);
        $this->get(route('planning.output-download', $out).'?attachment=0')->assertOk();
    }

    public function test_shared_contributor_uses_owner_output_without_duplicate_credit(): void
    {
        [$office,$root,$target] = $this->root('shared', 1);
        $a = $this->office('division', $office);
        $b = $this->office('division', $office);
        [$pa,$ta] = $this->link($root, $target, $a, 'dpcr', 1);
        $this->approve($pa);
        [$pb,$tb] = $this->link($root, $target, $b, 'dpcr', 1);
        $tb->update(['contributes_only' => true, 'quantity' => 0]);
        $this->approve($pb);
        $this->actingAs($a->head)->post(route('planning.output', $ta), ['reference' => 'EVENT-1', 'details' => 'One joint event', 'quantity' => 1, 'evidence' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $out = PlanningOutput::sole();
        $this->actingAs($b->head)->post(route('planning.contribution', $out), ['performance_target_id' => $tb->id, 'details' => 'Contributed logistics'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('planning_contributions', 1);
        $this->assertEquals(1, $this->engine->summary($target)['reported']);
        $this->assertNull($this->engine->summary($tb)['reported']);
    }

    public function test_new_period_activation_is_explicit_and_old_periods_stay_legacy(): void
    {
        $office = $this->office('office');
        $period = EvaluationCycle::create(['year' => 2028, 'semester' => '1st Semester', 'status' => 'open']);
        $this->actingAs($this->reviewer)->post(route('planning.activate', $period), ['planning_readiness' => 'Verified reporting hierarchy, reviewers and current policy.', 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($period->fresh()->planning_enabled);
        $old = EvaluationCycle::create(['year' => 2025, 'semester' => '1st Semester', 'status' => 'closed']);
        $this->assertFalse($old->planning_enabled);
        $this->actingAs($office->head)->get(route('planning.index'))->assertOk()->assertSee('Create a commitment plan');
        $this->actingAs($this->reviewer)->getJson('/api/planning')->assertOk()->assertJsonStructure(['html']);
    }

    public function test_allocation_drivers_require_relevance_and_record_explanation(): void
    {
        [$office,$root,$target] = $this->root();
        $d = $this->office('division', $office);
        $payload = ['office_id' => $d->id, 'name' => 'Cases', 'source' => 'Approved workload forecast for this period', 'value' => 60, 'capacity' => 60, 'valid_until' => today()->addYear()->toDateString()];
        $this->actingAs($office->head)->post(route('planning.driver', $target), $payload)->assertStatus(422);
        $this->post(route('planning.eligibility', $target), ['office_id' => $d->id, 'reason' => 'Processing division mandate'])->assertRedirect();
        $this->post(route('planning.driver', $target), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $s = $this->engine->suggestion($target);
        $this->assertEquals(60, array_sum($s['shares']));
        $this->assertEquals(40, $s['unallocated']);
    }

    public function test_final_rating_is_separate_and_requires_validated_evidence(): void
    {
        [$office,$root,$target] = $this->root('count', 10);
        $ratings = ['quality_rating' => 4, 'efficiency_rating' => 4, 'timeliness_rating' => 4, 'remarks' => 'Evidence reviewed'];
        $this->actingAs($this->reviewer)->post(route('planning.rate', $target), $ratings)->assertStatus(422);
        $this->actingAs($office->head)->post(route('planning.output', $target), ['reference' => 'FINAL-1', 'details' => 'Completed requests', 'quantity' => 10, 'evidence' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')])->assertRedirect();
        $this->actingAs($this->reviewer)->post(route('planning.output-validate', PlanningOutput::sole()))->assertRedirect();
        $this->post(route('planning.rate', $target), $ratings)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $root->report->fresh()->status);
        $this->actingAs($office->head)->patch(route('report-workflow.submit', ['opcr', $root->report_id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->reviewer)->patch(route('report-workflow.approve', ['opcr', $root->report_id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $root->report->fresh()->status);
        $this->get(route('reports.finished', ['opcr', $root->report_id]))->assertOk()->assertSee('Validated: 10');
    }

    public function test_old_confirmed_shares_remain_reserved_during_an_amendment(): void
    {
        [$office,$root,$source] = $this->root();
        $division = $this->office('division', $office);
        [$p,$t] = $this->link($root, $source, $division, 'dpcr', 60);
        $this->approve($p);
        $this->engine->amend($p, $division->head, 'Review our office workload');
        $this->assertEquals(60, $this->engine->used($source));
        $this->assertEquals(40, $this->engine->summary($source)['unallocated']);
        $this->assertEquals(1, $p->fresh()->approved_revision);
    }

    public function test_upward_amendment_keeps_eligibility_and_the_approved_original(): void
    {
        [$office,$root,$source] = $this->root();
        $d = $this->office('division', $office);
        DB::table('target_eligibilities')->insert(['performance_target_id' => $source->id, 'office_id' => $d->id, 'reason' => 'Processing mandate', 'confirmed_by' => $office->head_id]);
        $this->actingAs($d->head)->post(route('planning.proposal'), ['evaluation_cycle_id' => $this->cycle->id, 'amends_target_id' => $source->id, ...$this->data('count', 80)])->assertRedirect()->assertSessionHasNoErrors();
        $p = TargetProposal::sole();
        $this->assertEquals($office->id, $p->review_office_id);
        $this->actingAs($office->head)->post(route('planning.proposal-review', $p), ['decision' => 'materialize', 'root_plan_id' => $root->id, 'review_notes' => 'Revised forecast reviewed'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(100, $source->fresh()->quantity);
        $this->assertEquals(80, $root->fresh()->targets()->sole()->quantity);
        $this->assertDatabaseCount('performance_targets', 2);
        $this->assertDatabaseCount('target_eligibilities', 2);
    }

    public function test_parent_and_child_reductions_publish_only_after_both_are_reviewed(): void
    {
        [$office,$root,$source] = $this->root();
        $division = $this->office('division', $office);
        [$p,$t] = $this->link($root, $source, $division, 'dpcr', 60);
        $this->approve($p);
        $this->engine->amend($root, $office->head, 'Reduce forecast with reviewed office allocation');
        $root->refresh();
        $newRoot = $root->targets()->sole();
        $newRoot->update(['quantity' => 50]);
        $this->engine->amend($p, $division->head, 'Agree to the revised 50-request share');
        $p->refresh();
        $newChild = $p->targets()->sole();
        $newChild->update(['parent_target_id' => $newRoot->id, 'source_revision' => $root->revision, 'quantity' => 50, 'needs_reconciliation' => false]);
        $this->engine->submit($p, $division->head);
        $p->refresh();
        $this->engine->approve($p, $this->reviewer);
        $this->assertSame('endorsed', $p->fresh()->state);
        $this->assertEquals(60, $this->engine->used($source));
        $this->approve($root->fresh());
        $this->assertSame('approved', $p->fresh()->state);
        $this->assertEquals(2, $p->fresh()->approved_revision);
        $this->assertEquals(50, $this->engine->used($newRoot));
        $this->assertEquals(60, $t->fresh()->quantity);
    }

    public function test_stale_or_missing_inputs_require_manual_quantities(): void
    {
        [$office,$root,$source] = $this->root();
        $division = $this->office('division', $office);
        DB::table('target_eligibilities')->insert(['performance_target_id' => $source->id, 'office_id' => $division->id, 'reason' => 'Applicable', 'confirmed_by' => $office->head_id]);
        DB::table('planning_drivers')->insert(['performance_target_id' => $source->id, 'office_id' => $division->id, 'name' => 'Requests', 'value' => 50, 'source' => 'Outdated input', 'valid_until' => today()->subDay()->toDateString(), 'confirmed_by' => $office->head_id]);
        $this->assertSame([], $this->engine->suggestion($source)['shares']);
        $this->assertArrayHasKey('requires_manual', $this->engine->suggestion($source));
    }

    public function test_document_drafts_enter_opcr_instead_of_assigning_directly(): void
    {
        $office = $this->office('office');
        $plan = $this->plan($office, 'opcr');
        $doc = TargetDocument::create(['created_by' => $office->head_id, 'evaluation_cycle_id' => $this->cycle->id, 'original_name' => 'policy.pdf', 'path' => 'policy.pdf', 'mime_type' => 'application/pdf', 'status' => 'review']);
        $draft = $doc->drafts()->create(['proposal' => ['title' => 'Source target', 'source_excerpt' => 'Approved output 1', 'cd_only' => false, 'classification_reason' => 'Office duty', 'suggested_offices' => []]]);
        $this->actingAs($office->head)->get(route('target-drafts.review', $draft))->assertOk()->assertSee('Review extracted OPCR target');
        $this->post(route('planning.import', [$plan, $draft]), $this->data())->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('planning.import', [$plan, $draft]), $this->data())->assertStatus(422);
        $this->assertDatabaseCount('performance_targets', 1);
        $this->assertDatabaseCount('office_targets', 0);
        $this->post(route('target-drafts.assign', $draft), [])->assertStatus(422);
    }
}
