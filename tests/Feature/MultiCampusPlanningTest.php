<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CommitmentPlan;
use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\PerformanceTarget;
use App\Models\PlanningGrant;
use App\Models\PlanningOutput;
use App\Models\TargetDocument;
use App\Models\TargetProposal;
use App\Models\TargetScopeDraft;
use App\Models\User;
use App\Services\CommitmentPlanning;
use App\Services\GuidedTargetPlanning;
use App\Services\PcrCompliance;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiCampusPlanningTest extends TestCase
{
    use RefreshDatabase;

    private CommitmentPlanning $engine;

    private User $admin;

    private EvaluationCycle $cycle;

    private Office $university;

    private array $campuses;

    private array $roots;

    private array $departments;

    private CommitmentPlan $root;

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
        foreach (['North', 'South'] as $name) {
            $campus = Campus::create(['name' => $name.' campus', 'code' => $name]);
            $this->campuses[] = $campus;
            $branch = $this->office('office', $this->university, $campus);
            $this->roots[] = $branch;
            $this->departments[] = $this->office('division', $branch, $campus);
        }
        $this->cycle = EvaluationCycle::create(['year' => 2027, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => 'open', 'planning_enabled' => true, 'multi_campus_enabled' => true, 'university_office_id' => $this->university->id]);
        $this->root = $this->engine->createPlan($this->university->head, $this->cycle, $this->university, 'opcr', null);
    }

    private function office(string $level, ?Office $parent, ?Campus $campus): Office
    {
        $o = Office::factory()->create(['campus' => null, 'campus_id' => $campus?->id, 'organization_scope' => $campus ? 'campus' : 'university', 'parent_office_id' => $parent?->id, 'planning_level' => $level,
            'mandate' => 'Manage international student exchange placements and verified academic mobility', 'hierarchy_verified_at' => now(), 'planning_reviewer_id' => $this->admin->id]);
        $head = User::factory()->create(['office_id' => $o->id, 'planning_duties' => 'Arrange international student exchanges']);
        $head->assignRole('Supervisor');
        $o->update(['head_id' => $head->id]);

        return $o->fresh();
    }

    private function payload(string $basis = 'total', float $quantity = 2): array
    {
        return ['target' => '2 exchange students deployed abroad', 'success_indicator' => 'Students deployed to the approved partner institution', 'measurement' => 'count', 'quantity' => $quantity,
            'unit' => 'students', 'whole_numbers' => true, 'weight' => 25, 'deadline' => '2027-06-30', 'standards' => 'Approved exchange documents and deployment before deadline', 'source_evidence' => 'Reviewed institutional mobility plan, indicator 1',
            'coverage' => 'university', 'campus_ids' => array_map(fn ($c) => $c->id, $this->campuses), 'recipient_ids' => array_map(fn ($o) => $o->id, $this->departments), 'quantity_basis' => $basis, 'matching_reason' => 'Selected departments have verified international student exchange duties.'];
    }

    private function target(string $basis = 'total', float $quantity = 2): PerformanceTarget
    {
        $data = app(GuidedTargetPlanning::class)->normalize($this->root, $this->payload($basis, $quantity));

        return $this->engine->addTarget($this->root, $this->university->head, $data);
    }

    private function approve(CommitmentPlan $plan): void
    {
        $this->engine->submit($plan, User::findOrFail($plan->prepared_by));
        $plan->refresh();
        if ($plan->employee_id) {
            $plan->update(['agreed_at' => now()]);
        }
        $this->engine->approve($plan, $this->admin, ['pmt_reviewer' => 'External PMT', 'university_approver' => 'External approving official', 'approved_on' => '2026-11-01', 'evidence' => 'approval.pdf']);
        $plan->refresh();
    }

    private function child(CommitmentPlan $parent, PerformanceTarget $source, Office $office, string $level, float $quantity, ?User $person = null): array
    {
        if (! DB::table('target_eligibilities')->where('performance_target_id', $source->id)->where('office_id', $office->id)->where('employee_id', $person?->id)->exists()) {
            DB::table('target_eligibilities')->insert(['performance_target_id' => $source->id, 'office_id' => $office->id, 'employee_id' => $person?->id, 'reason' => 'Verified exchange duties', 'confirmed_by' => $parent->prepared_by]);
        }
        $p = $this->engine->createPlan($person ?? $office->head, $this->cycle, $office, $level, $parent, $person);
        $t = $this->engine->addTarget($p, $person ?? $office->head, ['target' => $source->target, 'quantity' => $quantity, 'weight' => 10], $source);

        return [$p, $t];
    }

    public function test_total_and_per_department_preview_and_private_idempotent_confirmation(): void
    {
        $this->actingAs($this->university->head)->get(route('planning.scope-create', $this->root))->assertOk()->assertSee('Combined total')->assertSee('Responsible departments');
        $this->post(route('planning.scope-store', $this->root), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $draft = TargetScopeDraft::sole();
        $this->get(route('planning.scope-show', $draft))->assertOk()->assertSee('Calculated commitment')->assertSee('2 students');
        $this->assertSame(0, PerformanceTarget::count());
        $this->actingAs($this->admin)->get(route('planning.scope-show', $draft))->assertForbidden();
        $this->actingAs($this->university->head)->post(route('planning.scope-confirm', $draft), ['confirmed' => 1])->assertRedirect();
        $this->post(route('planning.scope-confirm', $draft), ['confirmed' => 1])->assertRedirect();
        $this->assertSame(1, PerformanceTarget::count());
        $this->assertEquals(2, PerformanceTarget::sole()->quantity);
        $this->assertSame('draft', $this->root->fresh()->state);
        $this->assertEquals(4, app(GuidedTargetPlanning::class)->normalize($this->root, $this->payload('per_department'))['quantity']);
        $this->get('/api/planning/'.$this->root->id.'/scope/new')->assertOk()->assertJsonStructure(['html']);
    }

    public function test_full_five_level_chain_reconciles_one_hundred_unique_outputs(): void
    {
        $source = $this->target('total', 100);
        $this->approve($this->root);
        foreach ([60, 40] as $i => $share) {
            [$cp, $ct] = $this->child($this->root, $source, $this->roots[$i], 'opcr', $share);
            $this->approve($cp);
            $this->assertTrue($this->engine->eligible($ct, $this->engine->createPlan($this->departments[$i]->head, $this->cycle, $this->departments[$i], 'dpcr', $cp)));
            [$dp, $dt] = $this->child($cp, $ct, $this->departments[$i], 'dpcr', $share);
            $this->approve($dp);
            $unit = $this->office('unit', $this->departments[$i], $this->campuses[$i]);
            [$up, $ut] = $this->child($dp, $dt, $unit, 'upcr', $share);
            $this->approve($up);
            $employee = User::factory()->create(['office_id' => $unit->id, 'planning_duties' => 'Prepare and verify student exchange deployment documents']);
            $employee->assignRole('Employee');
            [$ip, $it] = $this->child($up, $ut, $unit, 'ipcr', $share, $employee);
            $this->approve($ip);
            $this->actingAs($employee)->post(route('planning.output', $it), ['reference' => 'EXCHANGE-'.$i, 'details' => 'Verified deployment batch', 'quantity' => $share, 'evidence' => UploadedFile::fake()->create('evidence.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
            $out = PlanningOutput::where('performance_target_id', $it->id)->sole();
            $this->actingAs($this->admin)->post(route('planning.output-validate', $out))->assertRedirect();
            $this->get(route('ipcrs.show', $ip->report))->assertRedirect(route('planning.show', $ip));
            $this->get(route('planning.show', $ip))->assertOk()->assertSee($this->university->name)->assertSee($this->roots[$i]->name);
            $this->assertSame($source->id, $it->root_target_id);
            $this->assertSame('draft', $ip->report->fresh()->status);
        }
        $summary = $this->engine->summary($source);
        $this->assertEquals(100, $summary['validated']);
        $this->assertEquals(0, $summary['unallocated']);
        $this->assertCount(2, $summary['outputs']);
        $export = $this->get(route('planning.export', [$this->root, 'excel']))->assertOk();
        $this->assertStringContainsString('Responsible departments', $export->streamedContent());
        $this->get(route('planning.export', [$this->root, 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_unselected_department_cannot_fetch_even_with_manual_eligibility(): void
    {
        $source = $this->target();
        $other = $this->office('division', $this->roots[0], $this->campuses[0]);
        [$cp, $ct] = $this->child($this->root, $source, $this->roots[0], 'opcr', 1);
        $dp = $this->engine->createPlan($other->head, $this->cycle, $other, 'dpcr', $cp);
        DB::table('target_eligibilities')->insert(['performance_target_id' => $ct->id, 'office_id' => $other->id, 'reason' => 'Forged eligibility', 'confirmed_by' => $this->admin->id]);
        $this->assertFalse($this->engine->eligible($ct, $dp));
        $this->actingAs($other->head)->get(route('planning.show', $dp))->assertOk()->assertDontSee('Students deployed to the approved partner institution');
    }

    public function test_per_department_share_is_enforced_at_campus_branch(): void
    {
        $source = $this->target('per_department');
        $this->expectException(ValidationException::class);
        $this->child($this->root, $source, $this->roots[0], 'opcr', 3);
    }

    public function test_campus_opcr_cannot_skip_university_root_or_take_another_campus_parent(): void
    {
        $this->actingAs($this->roots[0]->head)->post(route('planning.store'), ['evaluation_cycle_id' => $this->cycle->id, 'office_id' => $this->roots[0]->id, 'level' => 'opcr'])->assertSessionHasErrors();
        $this->expectException(ValidationException::class);
        $this->engine->createPlan($this->departments[0]->head, $this->cycle, $this->departments[0], 'dpcr', $this->root);
    }

    public function test_role_without_scope_does_not_grant_cross_campus_access_and_central_grant_does(): void
    {
        $source = $this->target();
        [$a] = $this->child($this->root, $source, $this->roots[0], 'opcr', 1);
        [$b] = $this->child($this->root, $source, $this->roots[1], 'opcr', 1);
        $planner = User::factory()->create();
        $planner->assignRole('Planning Officer');
        $this->actingAs($planner)->get(route('planning.show', $a))->assertForbidden();
        PlanningGrant::create(['user_id' => $planner->id, 'campus_id' => $this->campuses[0]->id, 'scope' => 'campus', 'reason' => 'Assigned campus planner', 'granted_by' => $this->admin->id]);
        $this->get(route('planning.index'))->assertOk()->assertSee($this->university->name)->assertDontSee($this->roots[1]->name);
        $this->get(route('planning.show', $a))->assertOk();
        $this->get('/api/planning/'.$b->id)->assertForbidden();
        $this->get(route('planning.export', [$b, 'pdf']))->assertForbidden();
        $this->get(route('opcrs.show', $b->report_id))->assertNotFound();
        $this->get(route('opcrs.index'))->assertOk()->assertDontSee($this->roots[1]->name);
        PlanningGrant::create(['user_id' => $planner->id, 'scope' => 'central', 'reason' => 'University-wide planning responsibility', 'granted_by' => $this->admin->id]);
        $this->get(route('planning.show', $b))->assertOk();
        $this->get(route('planning.export', [$b, 'excel']))->assertOk();
    }

    public function test_approved_scope_is_immutable_and_amendment_preserves_original_and_marks_children(): void
    {
        $source = $this->target();
        $this->approve($this->root);
        [$cp, $ct] = $this->child($this->root, $source, $this->roots[0], 'opcr', 1);
        $this->approve($cp);
        $this->actingAs($this->university->head)->post(route('planning.target-update', $source), $this->payload('per_department'))->assertSessionHasErrors();
        $before = $source->scope_metadata;
        $this->engine->amend($this->root, $this->university->head, 'Reviewed revised departmental coverage');
        $this->assertSame($before, $source->fresh()->scope_metadata);
        $this->assertTrue($ct->fresh()->needs_reconciliation);
        $this->assertSame(2, $this->root->fresh()->revision);
    }

    public function test_ai_failure_does_not_create_target_and_manual_confirmation_remains_available(): void
    {
        $this->actingAs($this->university->head)->post(route('planning.scope-store', $this->root), $this->payload())->assertRedirect();
        $draft = TargetScopeDraft::sole();
        $this->post(route('planning.scope-suggest', $draft))->assertSessionHasErrors('ai');
        $this->assertSame(0, PerformanceTarget::count());
        $this->post(route('planning.scope-confirm', $draft), ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, PerformanceTarget::count());
    }

    public function test_missing_mandate_or_rate_per_department_needs_review(): void
    {
        $this->departments[0]->update(['mandate' => null]);
        $this->actingAs($this->university->head)->post(route('planning.scope-store', $this->root), $this->payload())->assertSessionHasErrors();
        $this->departments[0]->update(['mandate' => 'Manage exchange students']);
        $this->post(route('planning.scope-store', $this->root), [...$this->payload('per_department'), 'measurement' => 'rate'])->assertSessionHasErrors();
        $this->assertSame(0, TargetScopeDraft::count());
    }

    public function test_existing_period_cannot_be_switched_and_no_configuration_is_fabricated(): void
    {
        $this->actingAs($this->admin)->get(route('planning.campuses'))->assertOk()->assertSee('Campus catalogue');
        $this->post(route('planning.campus-activate', $this->cycle), ['university_office_id' => $this->university->id, 'planning_readiness' => str_repeat('verified ', 4), 'confirmed' => 1])->assertUnprocessable();
        $this->post(route('planning.campus-classify', $this->university), ['organization_scope' => 'campus', 'campus_id' => $this->campuses[0]->id])->assertUnprocessable();
        $this->assertEquals(2, Campus::count());
    }

    public function test_personnel_editor_can_assign_configured_campus_office_without_changing_password(): void
    {
        $person = User::factory()->create();
        $person->assignRole('Employee');
        $hash = $person->password;
        $this->actingAs($this->admin)->patch(route('employees.update', $person), ['name' => $person->name, 'email' => $person->email, 'office_id' => $this->departments[1]->id, 'role' => 'Employee'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($this->departments[1]->id, $person->fresh()->office_id);
        $this->assertSame($hash, $person->fresh()->password);
    }

    public function test_upward_proposal_uses_guided_confirmation_before_becoming_root_target(): void
    {
        $data = $this->payload();
        $this->actingAs($this->departments[0]->head)->post(route('planning.proposal'), [...$data, 'evaluation_cycle_id' => $this->cycle->id])->assertRedirect()->assertSessionHasNoErrors();
        $proposal = TargetProposal::sole();
        $this->assertEquals($this->roots[0]->id, $proposal->review_office_id);
        $this->actingAs($this->roots[0]->head)->post(route('planning.proposal-review', $proposal), ['decision' => 'forward', 'review_notes' => 'Fits campus mobility duties'])->assertRedirect();
        $this->assertEquals($this->university->id, $proposal->fresh()->review_office_id);
        $this->actingAs($this->university->head)->post(route('planning.proposal-review', $proposal), ['decision' => 'materialize', 'root_plan_id' => $this->root->id, 'review_notes' => 'Review quantities and recipients first'])->assertRedirect(route('planning.scope-create', ['plan' => $this->root, 'proposal_id' => $proposal->id]));
        $this->assertSame('pending', $proposal->fresh()->state);
        $this->assertSame(0, PerformanceTarget::count());
        $this->post(route('planning.scope-store', $this->root), [...$data, 'proposal_id' => $proposal->id])->assertRedirect()->assertSessionHasNoErrors();
        $draft = TargetScopeDraft::sole();
        $this->post(route('planning.scope-confirm', $draft), ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $proposal->fresh()->state);
        $this->assertEquals($this->root->id, $proposal->fresh()->root_plan_id);
    }

    public function test_campus_endorsement_keeps_external_evidence_until_parent_approval(): void
    {
        $source = $this->target();
        [$cp] = $this->child($this->root, $source, $this->roots[0], 'opcr', 1);
        $this->approve($cp);
        $this->assertSame('endorsed', $cp->state);
        $this->assertEquals(2, $this->engine->summary($source)['unallocated']);
        $this->approve($this->root);
        $this->assertSame('approved', $cp->fresh()->state);
        $this->assertSame('approval.pdf', $cp->events()->where('action', 'Commitments approved')->first()->data['evidence']);
        $this->assertEquals(1, $this->engine->summary($source)['unallocated']);
    }

    public function test_university_head_personal_ipcr_follows_actual_accountable_level(): void
    {
        $source = $this->target();
        $this->approve($this->root);
        $head = $this->university->head;
        $ip = $this->engine->createPlan($head, $this->cycle, $this->university, 'ipcr', $this->root, $head, 'University head accountable for institutional coordination');
        DB::table('target_eligibilities')->insert(['performance_target_id' => $source->id, 'office_id' => $this->university->id, 'employee_id' => $head->id, 'reason' => 'Recorded head responsibilities', 'confirmed_by' => $this->admin->id]);
        $line = $this->engine->addTarget($ip, $head, ['target' => 'Coordinate exchanges', 'quantity' => 1, 'weight' => 10], $source);
        $this->assertEquals($source->id, $line->root_target_id);
        $this->assertSame(['ipcr', 'opcr'], app(PcrCompliance::class)->requiredReports($head));
    }

    public function test_extracted_document_uses_same_scope_flow_and_source_cannot_be_replaced(): void
    {
        $document = TargetDocument::create(['created_by' => $this->admin->id, 'evaluation_cycle_id' => $this->cycle->id, 'original_name' => 'institution.pdf', 'path' => 'private-institution.pdf', 'mime_type' => 'application/pdf', 'status' => 'review']);
        $extracted = $document->drafts()->create(['proposal' => ['title' => 'Exchange students', 'source_excerpt' => 'Source passage: deploy 2 students', 'classification_reason' => 'Mobility responsibility']]);
        $this->actingAs($this->admin)->get(route('planning.scope-create', ['plan' => $this->root, 'document_draft_id' => $extracted->id]))->assertOk()->assertSee('Source passage: deploy 2 students');
        $this->post(route('planning.scope-store', $this->root), [...$this->payload(), 'document_draft_id' => $extracted->id])->assertRedirect()->assertSessionHasNoErrors();
        $draft = TargetScopeDraft::sole();
        $this->post(route('planning.scope-confirm', $draft), ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStringContainsString('Source passage: deploy 2 students', PerformanceTarget::sole()->source_evidence);
        $this->assertNotNull($extracted->fresh()->performance_target_id);
        $this->post(route('planning.scope-store', $this->root), [...$this->payload(), 'document_draft_id' => $extracted->id])->assertForbidden();
    }

    public function test_new_period_activation_requires_grants_and_preserves_existing_selection(): void
    {
        $new = EvaluationCycle::create(['year' => 2028, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => 'open']);
        $payload = ['university_office_id' => $this->university->id, 'planning_readiness' => 'Verified campuses, duties, reviewer assignments and allocation inputs.', 'confirmed' => 1];
        $this->actingAs($this->admin)->post(route('planning.campus-activate', $new), $payload)->assertUnprocessable();
        $planner = User::factory()->create();
        $planner->assignRole('Planning Officer');
        foreach ([null, ...$this->campuses] as $campus) {
            PlanningGrant::create(['user_id' => $planner->id, 'scope' => $campus ? 'campus' : 'central', 'campus_id' => $campus?->id, 'reason' => 'Verified assignment', 'granted_by' => $this->admin->id]);
        }
        $this->post(route('planning.campus-activate', $new), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($new->fresh()->multi_campus_enabled);
        $this->assertEquals($this->university->id, $new->fresh()->university_office_id);
        $this->assertSame('open', $this->cycle->fresh()->status);
    }

    public function test_campus_grant_keeps_other_branch_allocations_and_evidence_private(): void
    {
        $source = $this->target();
        $this->approve($this->root);
        foreach ([0, 1] as $i) {
            [$campusPlan, $campusTarget] = $this->child($this->root, $source, $this->roots[$i], 'opcr', 1);
            $this->approve($campusPlan);
            [$division, $divisionTarget] = $this->child($campusPlan, $campusTarget, $this->departments[$i], 'dpcr', 1);
            $this->approve($division);
            if ($i === 1) {
                $this->actingAs($this->departments[$i]->head)->post(route('planning.output', $divisionTarget), ['reference' => 'SOUTH-DEPLOYMENT', 'details' => 'Private south campus evidence', 'quantity' => 1, 'evidence' => UploadedFile::fake()->create('private-proof.pdf', 1, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
            }
        }
        $planner = User::factory()->create();
        $planner->assignRole('Planning Officer');
        PlanningGrant::create(['user_id' => $planner->id, 'campus_id' => $this->campuses[0]->id, 'scope' => 'campus', 'reason' => 'North campus assignment', 'granted_by' => $this->admin->id]);
        $this->actingAs($planner)->get(route('office-targets.index'))->assertOk()->assertSee($this->departments[0]->name)->assertDontSee($this->departments[1]->name);
        $output = PlanningOutput::sole();
        $this->get(route('planning.output-download', $output))->assertForbidden();
        $this->get('/api/planning/outputs/'.$output->id.'/download')->assertForbidden();
    }
}
