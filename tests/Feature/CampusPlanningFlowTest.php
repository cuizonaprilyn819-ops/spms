<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CommitmentPlan;
use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\PerformanceTarget;
use App\Models\PlanningGrant;
use App\Models\PlanningOutput;
use App\Models\TargetProposal;
use App\Models\TargetScopeDraft;
use App\Models\User;
use App\Notifications\TargetProposalNotification;
use App\Services\CommitmentPlanning;
use App\Services\GuidedTargetPlanning;
use App\Services\PlanningOrganization;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampusPlanningFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private Office $university;

    private Office $campus;

    private Office $otherCampus;

    private Office $department;

    private Office $unit;

    private EvaluationCycle $cycle;

    private CommitmentPlanning $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->engine = app(CommitmentPlanning::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrator');
        $this->university = $this->office('office', null, null);
        $north = Campus::create(['name' => 'North campus', 'code' => 'NORTH']);
        $south = Campus::create(['name' => 'South campus', 'code' => 'SOUTH']);
        $this->campus = $this->office('office', $this->university, $north);
        $this->otherCampus = $this->office('office', $this->university, $south);
        $this->department = $this->office('division', $this->campus, $north);
        $this->unit = $this->office('unit', $this->department, $north);
        $this->employee = User::factory()->create(['office_id' => $this->unit->id, 'planning_duties' => 'Arrange international student exchange deployments']);
        $this->employee->assignRole('Employee');
        $this->cycle = EvaluationCycle::create(['year' => 2027, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => 'open',
            'planning_enabled' => true, 'multi_campus_enabled' => true, 'planning_root_level' => 'campus']);
    }

    private function office(string $level, ?Office $parent, ?Campus $campus): Office
    {
        $office = Office::factory()->create(['campus' => null, 'campus_id' => $campus?->id, 'organization_scope' => $campus ? 'campus' : 'university',
            'planning_level' => $level, 'parent_office_id' => $parent?->id, 'mandate' => 'Manage international student exchange deployments',
            'hierarchy_verified_at' => now(), 'planning_reviewer_id' => $this->admin->id]);
        $head = User::factory()->create(['office_id' => $office->id, 'planning_duties' => 'Manage and verify international student exchange deployments']);
        $head->assignRole('Supervisor');
        $office->update(['head_id' => $head->id]);

        return $office->fresh();
    }

    private function data(): array
    {
        return ['target' => '2 exchange students deployed abroad', 'success_indicator' => 'Deploy two students with verified documents by the deadline',
            'measurement' => 'count', 'quantity' => 2, 'unit' => 'students', 'whole_numbers' => true, 'weight' => 10, 'deadline' => '2027-06-30',
            'standards' => 'Verified exchange and travel documents', 'source_evidence' => 'Synthetic training target only',
            'coverage' => 'campus', 'campus_ids' => [$this->campus->campus_id], 'recipient_ids' => [$this->department->id],
            'quantity_basis' => 'total', 'matching_reason' => 'Recorded exchange deployment duties'];
    }

    private function root(): CommitmentPlan
    {
        return $this->engine->createPlan($this->campus->head, $this->cycle, $this->campus, 'opcr', null);
    }

    private function approve(CommitmentPlan $plan): void
    {
        $this->engine->submit($plan, User::findOrFail($plan->prepared_by));
        if ($plan->employee_id) {
            $plan->update(['agreed_at' => now()]);
        }
        $this->engine->approve($plan->fresh(), $this->admin, ['pmt_reviewer' => 'Test PMT', 'university_approver' => 'Test approver', 'approved_on' => '2026-11-01', 'evidence' => 'test-only.pdf']);
        $plan->refresh();
    }

    public function test_campus_root_runs_the_full_downward_chain_without_a_university_opcr(): void
    {
        $root = $this->root();
        $line = $this->engine->addTarget($root, $this->campus->head, app(GuidedTargetPlanning::class)->normalize($root, $this->data()));
        $this->assertNull($root->parent_id);
        $this->assertSame('Campus OPCR', $root->level_label);
        $this->approve($root);
        $plans = [$root];
        $targets = [$line];
        foreach ([['dpcr', $this->department, null], ['upcr', $this->unit, null], ['ipcr', $this->unit, $this->employee]] as [$level, $office, $person]) {
            DB::table('target_eligibilities')->updateOrInsert(['performance_target_id' => $line->id, 'office_id' => $office->id, 'employee_id' => $person?->id], ['reason' => 'Verified exchange duties', 'confirmed_by' => $this->admin->id]);
            $parent = end($plans);
            $actor = $person ?? $office->head;
            $plan = $this->engine->createPlan($actor, $this->cycle, $office, $level, $parent, $person);
            $line = $this->engine->addTarget($plan, $actor, ['target' => 'Arrange the assigned student exchanges', 'quantity' => 2, 'weight' => 10], $line);
            $this->approve($plan);
            $plans[] = $plan;
            $targets[] = $line;
        }
        $this->actingAs($this->employee)->post(route('planning.output', $line), ['reference' => 'DEMO-BATCH-2', 'quantity' => 2, 'details' => 'Two simulated exchange deployments', 'evidence' => UploadedFile::fake()->create('demo.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $output = PlanningOutput::sole();
        $this->actingAs($this->admin)->post(route('planning.output-validate', $output))->assertRedirect()->assertSessionHasNoErrors();
        foreach ($targets as $target) {
            $this->assertEquals(2, $this->engine->summary($target)['validated']);
            $this->assertEquals($targets[0]->id, $target->root_target_id);
        }
        $this->assertSame(4, CommitmentPlan::count());
        $this->assertSame(1, PlanningOutput::count());
        $this->get(route('planning.show', $root))->assertOk()->assertSee('Campus OPCR')->assertDontSee('University OPCR');
        $export = $this->get(route('planning.export', [$root, 'excel']))->assertOk();
        $this->assertStringContainsString('Campus OPCR', $export->streamedContent());
        $this->assertSame('draft', $root->report->fresh()->status);
    }

    public function test_individual_proposal_moves_unit_department_campus_before_any_parent_plan_exists(): void
    {
        $this->actingAs($this->employee)->post('/api/planning/proposals', [...$this->data(), 'evaluation_cycle_id' => $this->cycle->id])->assertSuccessful();
        $proposal = TargetProposal::sole();
        $this->assertSame(0, CommitmentPlan::count());
        $this->assertSame(0, PerformanceTarget::count());
        $this->assertEquals($this->unit->id, $proposal->review_office_id);
        $this->get(route('planning.index'))->assertOk()->assertSee('Proposed quantity')->assertSee('2 students')->assertSee($this->employee->name)->assertSee('Synthetic training target only');
        $this->post(route('planning.proposal-review', $proposal), ['decision' => 'forward', 'review_notes' => 'Self-review'])->assertForbidden();
        foreach ([[$this->unit, $this->department], [$this->department, $this->campus]] as [$current, $next]) {
            $this->actingAs($current->head)->post(route('planning.proposal-review', $proposal), ['decision' => 'forward', 'review_notes' => 'Relevant to our recorded exchange duties'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertEquals($next->id, $proposal->fresh()->review_office_id);
        }
        $this->actingAs($this->campus->head)->post(route('planning.proposal-review', $proposal), ['decision' => 'forward', 'review_notes' => 'Must stop at campus'])->assertUnprocessable();
        $this->assertEquals($this->campus->id, $proposal->fresh()->review_office_id);
        $root = $this->root();
        $this->post(route('planning.proposal-review', $proposal), ['decision' => 'materialize', 'root_plan_id' => $root->id, 'review_notes' => 'Review the campus quantity and responsible department'])->assertRedirect(route('planning.scope-create', ['plan' => $root, 'proposal_id' => $proposal->id]));
        $this->post(route('planning.scope-store', $root), [...$this->data(), 'proposal_id' => $proposal->id])->assertRedirect()->assertSessionHasNoErrors();
        $draft = TargetScopeDraft::sole();
        $this->post(route('planning.scope-confirm', $draft), ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('planning.scope-confirm', $draft), ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $proposal->fresh()->state);
        $this->assertEquals($root->id, $proposal->fresh()->root_plan_id);
        $this->assertSame(1, PerformanceTarget::count());
        $this->assertEquals(2, PerformanceTarget::sole()->quantity);
        $this->assertSame('draft', $root->fresh()->state);
    }

    public function test_campus_root_without_a_university_office_is_verified(): void
    {
        $this->campus->update(['parent_office_id' => null]);
        $root = $this->root();
        $this->assertNull($root->parent_id);
        $this->assertSame('campus', $root->office->organization_scope);
    }

    public function test_campus_root_rejects_a_university_parent_and_another_campus_recipient(): void
    {
        $otherDepartment = $this->office('division', $this->otherCampus, $this->otherCampus->campusRecord);
        $root = $this->root();
        $this->actingAs($this->campus->head)->post(route('planning.scope-store', $root), [...$this->data(), 'recipient_ids' => [$otherDepartment->id], 'campus_ids' => [$this->otherCampus->campus_id]])->assertSessionHasErrors();
        $this->post(route('planning.scope-store', $root), [...$this->data(), 'coverage' => 'university'])->assertSessionHasErrors();
        $this->actingAs($this->university->head)->post(route('planning.store'), ['evaluation_cycle_id' => $this->cycle->id, 'office_id' => $this->university->id, 'level' => 'opcr'])->assertSessionHasErrors();
        $this->assertSame(1, CommitmentPlan::count());
        $this->assertSame(0, PerformanceTarget::count());
    }

    public function test_another_campus_planner_cannot_review_or_read_the_proposal(): void
    {
        $this->actingAs($this->employee)->post(route('planning.proposal'), [...$this->data(), 'evaluation_cycle_id' => $this->cycle->id])->assertRedirect();
        $proposal = TargetProposal::sole();
        $planner = User::factory()->create();
        $planner->assignRole('Planning Officer');
        PlanningGrant::create(['user_id' => $planner->id, 'scope' => 'campus', 'campus_id' => $this->otherCampus->campus_id, 'reason' => 'South only', 'granted_by' => $this->admin->id]);
        $this->actingAs($planner)->get(route('planning.index'))->assertOk()->assertDontSee('Proposal #'.$proposal->id.' ·');
        $this->post('/api/planning/proposals/'.$proposal->id.'/review', ['decision' => 'forward', 'review_notes' => 'Wrong campus'])->assertForbidden();
        $this->assertEquals($this->unit->id, $proposal->fresh()->review_office_id);
    }

    public function test_campus_head_proposal_stays_at_campus_and_notifies_an_independent_scoped_reviewer(): void
    {
        $planner = User::factory()->create();
        $planner->assignRole('Planning Officer');
        PlanningGrant::create(['user_id' => $planner->id, 'scope' => 'campus', 'campus_id' => $this->campus->campus_id, 'reason' => 'Campus planning', 'granted_by' => $this->admin->id]);
        $this->campus->update(['planning_reviewer_id' => $planner->id]);
        $this->actingAs($this->campus->head)->post(route('planning.proposal'), [...$this->data(), 'evaluation_cycle_id' => $this->cycle->id])->assertRedirect()->assertSessionHasNoErrors();
        $proposal = TargetProposal::sole();
        $this->assertEquals($this->campus->id, $proposal->review_office_id);
        $this->assertSame(1, $planner->notifications()->where('type', TargetProposalNotification::class)->count());
        $this->assertSame(0, $this->university->head->notifications()->where('type', TargetProposalNotification::class)->count());
        $this->actingAs($planner)->post(route('planning.proposal-review', $proposal), ['decision' => 'return', 'review_notes' => 'Clarify the source'])->assertRedirect();
        $this->actingAs($this->campus->head)->post(route('planning.proposal-resubmit', $proposal), $this->data())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($this->campus->id, $proposal->fresh()->review_office_id);
        $this->assertSame('pending', $proposal->fresh()->state);
    }

    public function test_new_campus_period_requires_campus_grants_but_no_university_or_central_grant(): void
    {
        $new = EvaluationCycle::create(['year' => 2028, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => 'open']);
        $payload = ['planning_root_level' => 'campus', 'planning_readiness' => 'Verified campus reporting lines, duties, reviewers and allocation inputs', 'confirmed' => 1];
        $this->actingAs($this->admin)->post(route('planning.campus-activate', $new), $payload)->assertUnprocessable();
        $planner = User::factory()->create();
        $planner->assignRole('Planning Officer');
        foreach ([$this->campus, $this->otherCampus] as $office) {
            PlanningGrant::create(['user_id' => $planner->id, 'scope' => 'campus', 'campus_id' => $office->campus_id, 'reason' => 'Verified assignment', 'granted_by' => $this->admin->id]);
        }
        $this->post(route('planning.campus-activate', $new), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('campus', $new->fresh()->planning_root_level);
        $this->assertFalse($new->fresh()->usesUniversityOpcr());
        $this->assertNull($new->fresh()->university_office_id);
        $this->assertSame('open', $this->cycle->fresh()->status);
        $this->post(route('planning.campus-activate', $new), [...$payload, 'planning_root_level' => 'university', 'university_office_id' => $this->university->id])->assertUnprocessable();
        $this->assertSame('campus', $new->fresh()->planning_root_level);
    }

    public function test_existing_university_period_keeps_its_original_five_level_behavior(): void
    {
        $this->cycle->update(['planning_root_level' => null, 'university_office_id' => $this->university->id]);
        $this->assertTrue($this->cycle->usesUniversityOpcr());
        $this->assertEquals($this->university->id, app(PlanningOrganization::class)->nextProposalOffice($this->cycle, $this->campus)->id);
        $this->actingAs($this->campus->head)->post(route('planning.store'), ['evaluation_cycle_id' => $this->cycle->id, 'office_id' => $this->campus->id, 'level' => 'opcr'])->assertSessionHasErrors();
        $this->assertSame(0, CommitmentPlan::count());
    }

    public function test_campus_combined_and_per_department_quantities_and_overallocation_are_checked(): void
    {
        $other = $this->office('division', $this->campus, $this->campus->campusRecord);
        $root = $this->root();
        $payload = [...$this->data(), 'recipient_ids' => [$this->department->id, $other->id]];
        $guided = app(GuidedTargetPlanning::class);
        $this->assertEquals(4, $guided->normalize($root, [...$payload, 'quantity_basis' => 'per_department'])['quantity']);
        $target = $this->engine->addTarget($root, $this->campus->head, $guided->normalize($root, $payload));
        $this->approve($root);
        foreach ([[$this->department, 1], [$other, 2]] as [$office, $quantity]) {
            $plan = $this->engine->createPlan($office->head, $this->cycle, $office, 'dpcr', $root);
            $this->engine->addTarget($plan, $office->head, ['target' => $target->target, 'quantity' => $quantity, 'weight' => 10], $target);
            if ($quantity === 1) {
                $this->approve($plan);
                $this->assertEquals(1, $this->engine->summary($target)['unallocated']);
            } else {
                $this->engine->submit($plan, $office->head);
                $this->actingAs($this->admin)->post(route('planning.transition', [$plan, 'approve']))->assertSessionHasErrors();
                $this->assertSame('submitted', $plan->fresh()->state);
            }
        }
        $this->assertEquals(1, $this->engine->summary($target)['unallocated']);
    }

    public function test_rejected_individual_proposal_never_becomes_a_commitment(): void
    {
        $this->actingAs($this->employee)->post(route('planning.proposal'), [...$this->data(), 'evaluation_cycle_id' => $this->cycle->id])->assertRedirect();
        $proposal = TargetProposal::sole();
        $this->actingAs($this->unit->head)->post(route('planning.proposal-review', $proposal), ['decision' => 'reject', 'review_notes' => 'Source evidence needs verification'])->assertRedirect();
        $this->assertSame('rejected', $proposal->fresh()->state);
        $this->post(route('planning.proposal-review', $proposal), ['decision' => 'forward', 'review_notes' => 'Cannot forward rejected work'])->assertUnprocessable();
        $this->assertSame(0, CommitmentPlan::count());
        $this->assertSame(0, PerformanceTarget::count());
    }
}
