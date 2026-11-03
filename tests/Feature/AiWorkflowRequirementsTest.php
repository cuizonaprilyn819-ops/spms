<?php

namespace Tests\Feature;

use App\Models\{EvaluationCycle, EvidenceAnalysis, Ipcr, Mov, Office, OfficeTarget, PerformanceTarget, TargetDocument, TargetMov, User, WorkflowAudit};
use App\Services\{EvidenceAi, TargetDocumentAi};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class AiWorkflowRequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test-key', 'spms.ai.model' => 'test-model']);
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    private function response(array $result): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($result)]]]]];
    }

    private function cycle(): EvaluationCycle
    {
        return EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open']);
    }

    public function test_mov_file_is_analyzed_and_report_uses_statement_with_audited_user_edits(): void
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee]);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $ipcr, 'created_by_user' => $employee]);
        $mov = Mov::create(['performance_target_id' => $target->id, 'title' => 'Training evidence', 'file_path' => 'attendance.pdf',
            'original_name' => 'attendance.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'file_size' => 20, 'uploaded_by' => $employee->id]);
        Storage::disk('public')->put('attendance.pdf', '%PDF-synthetic-test-evidence');
        Http::fake(['api.openai.com/*' => Http::response($this->response(['summary' => 'Signed attendance for one workshop.',
            'statement' => 'Conducted one workshop attended by 30 BSIT students.', 'supported' => true, 'satisfies_target' => true, 'target_assessment' => 'The evidence demonstrates the target.',
            'source_excerpts' => ['Page 1: Workshop attendance - 30 BSIT students.'], 'limitations' => 'No assessment results provided.']))]);
        config(['spms.ai.statement_template' => 'Completed [action] for [quantity] [beneficiaries] on [date].']);
        $this->actingAs($employee)->post(route('evidence.analyze', ['mov', $mov->id]))->assertRedirect();
        $this->assertSame('completed', $mov->analysis->status);
        Http::assertSent(fn ($request) => $request['input'][0]['content'][0]['file_data'] === 'data:application/pdf;base64,'.base64_encode('%PDF-synthetic-test-evidence')
            && str_contains($request['instructions'], 'past tense') && str_contains($request['input'][0]['content'][1]['text'], 'Completed [action]') && $request['store'] === false);
        $this->get(route('reports.show', 'ipcr'))->assertOk()->assertSee('Conducted one workshop attended by 30 BSIT students.');
        $this->put(route('evidence.statement', ['mov', $mov->id]), ['statement' => 'Delivered one web development workshop for 30 BSIT students.'])->assertRedirect();
        $this->get(route('reports.show', 'ipcr'))->assertOk()->assertSee('Delivered one web development workshop for 30 BSIT students.');
        $this->assertDatabaseHas('workflow_audits', ['subject_id' => $mov->id, 'subject_type' => Mov::class, 'actor_id' => $employee->id, 'action' => 'Report statement edited by uploader']);
        $other = User::factory()->create()->assignRole('Employee');
        $this->actingAs($other)->post(route('evidence.analyze', ['mov', $mov->id]))->assertForbidden();
        $this->put(route('evidence.statement', ['mov', $mov->id]), ['statement' => 'Tampered'])->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_office_target_mov_and_planning_evidence_use_the_same_file_analysis_flow(): void
    {
        $employee = User::factory()->create(['office_id' => Office::factory()->create(['campus' => 6])->id])->assignRole('Employee');
        $officeTarget = OfficeTarget::create(['created_by' => $employee->id, 'title' => 'Workshop', 'success_indicator' => 'One workshop', 'target_value' => 1,
            'unit_of_measure' => 'workshop', 'weight' => 20, 'deadline' => '2026-12-15', 'priority' => 'medium']);
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee]);
        $ipcr->update(['office_target_id' => $officeTarget->id]);
        $mov = TargetMov::create(['office_target_id' => $officeTarget->id, 'user_id' => $employee->id, 'office_id' => $employee->office_id,
            'accomplishment' => 'User claim', 'path' => 'workshop.pdf', 'original_name' => 'workshop.pdf']);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $ipcr, 'created_by_user' => $employee]);
        $output = \App\Models\PlanningOutput::create(['performance_target_id' => $target->id, 'root_target_id' => $target->id, 'reference' => 'WORKSHOP-1',
            'details' => 'Workshop reported', 'quantity' => 1, 'evidence' => ['path' => 'workshop.pdf', 'name' => 'workshop.pdf'], 'submitted_by' => $employee->id]);
        Storage::disk('local')->put('workshop.pdf', 'synthetic PDF');
        Http::fake(['api.openai.com/*' => Http::response($this->response(['summary' => 'Workshop evidence.', 'statement' => 'Delivered one BSIT workshop.',
            'supported' => true, 'satisfies_target' => true, 'target_assessment' => 'The evidence demonstrates the target.', 'source_excerpts' => ['Page 1: Workshop delivered.'], 'limitations' => '']))]);
        $this->actingAs($employee)->post(route('evidence.analyze', ['target-mov', $mov->id]))->assertRedirect();
        $this->get(route('reports.show', 'ipcr'))->assertOk()->assertSee('Delivered one BSIT workshop.');
        $this->post(route('evidence.analyze', ['planning-output', $output->id]))->assertRedirect();
        $this->assertSame('Delivered one BSIT workshop.', $output->fresh()->analysis->statement);
        $this->assertDatabaseCount('evidence_analyses', 2);
        Http::assertSentCount(2);
    }

    public function test_unreadable_mov_and_provider_failure_do_not_create_report_statements(): void
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $mov = Mov::create(['title' => 'Unreadable', 'file_path' => 'scan.jpg', 'original_name' => 'scan.jpg', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'file_size' => 10, 'uploaded_by' => $employee->id]);
        Storage::disk('public')->put('scan.jpg', 'synthetic');
        Http::fakeSequence()->push($this->response(['summary' => 'Unreadable scan.', 'statement' => 'Must be discarded.', 'supported' => false, 'satisfies_target' => false, 'target_assessment' => 'The target cannot be verified.', 'source_excerpts' => [], 'limitations' => 'Text cannot be read.']))->push([], 503);
        $this->actingAs($employee)->post(route('evidence.analyze', ['mov', $mov->id]))->assertRedirect();
        $this->assertNull($mov->fresh()->analysis->statement);
        Http::assertSent(fn ($request) => isset($request['input'][0]['content'][0]['image_url']));
        $this->put(route('evidence.statement', ['mov', $mov->id]), ['statement' => 'Invented accomplishment'])->assertStatus(422);
        $this->post(route('evidence.analyze', ['mov', $mov->id]))->assertRedirect();
        $this->assertSame('failed', $mov->fresh()->analysis->status);
        Storage::disk('public')->assertExists('scan.jpg');
    }

    public function test_ai_catalogue_and_schedule_use_only_campus_six_and_referenced_period(): void
    {
        $director = User::factory()->create()->assignRole('Campus Director');
        $office = Office::factory()->create(['campus' => 6, 'is_active' => true]);
        $other = Office::factory()->create(['campus' => 2, 'is_active' => true]);
        $cycle = $this->cycle();
        $document = TargetDocument::create(['created_by' => $director->id, 'evaluation_cycle_id' => $cycle->id, 'path' => 'opcr.pdf', 'original_name' => 'opcr.pdf', 'mime_type' => 'application/pdf']);
        Storage::disk('local')->put('opcr.pdf', 'synthetic');
        $proposal = ['program' => 'Staff development', 'program_outcome' => 'Trained staff', 'title' => 'Training', 'success_indicator' => 'Two workshops', 'target_value' => 2, 'unit_of_measure' => 'workshops', 'weight' => 20,
            'weight_reason' => 'Suggested priority; confirm locally.', 'deadline' => '2027-01-01', 'source_excerpt' => 'Two workshops by January 2027.', 'cd_only' => false, 'classification_reason' => 'IT training',
            'suggested_offices' => [['office_id' => $office->id, 'reason' => 'Training mandate', 'allocation_percentage' => 100, 'allocated_target_value' => 2]]];
        Http::fake(['api.openai.com/*' => Http::response($this->response(['summary' => 'Training', 'warnings' => '', 'targets' => [$proposal]]))]);
        $this->actingAs($director)->post(route('target-documents.analyze', $document))->assertRedirect();
        $draft = $document->drafts()->sole();
        $this->assertNull($draft->proposal['deadline']);
        $this->assertSame('2027-01-01', $draft->proposal['source_deadline']);
        Http::assertSent(function ($request) use ($office, $other) {
            $context = json_decode($request['input'][0]['content'][1]['text'], true);
            return array_column($context['offices'], 'id') === [$office->id]
                && $context['evaluation_period']['starts_on'] === '2026-07-01'
                && $context['evaluation_period']['ends_on'] === '2026-12-31';
        });
        $data = ['title' => 'Reviewed training', 'success_indicator' => 'Two workshops', 'target_value' => 2, 'unit_of_measure' => 'workshops', 'weight' => 25,
            'deadline' => '2027-01-01', 'priority' => 'high', 'office_ids' => [$office->id], 'allocations' => [$office->id => 2], 'confirmed' => 1];
        $this->post(route('target-drafts.assign', $draft), [...$data, 'deadline' => '2026-12-15', 'office_ids' => [$other->id], 'allocations' => [$other->id => 2]])->assertSessionHasErrors('office_ids.0');
        $this->assertDatabaseCount('office_targets', 0);
        Http::assertSentCount(1);
    }

    public function test_cd_can_replace_offices_and_edit_allocations_with_fresh_briefings_and_full_audit(): void
    {
        $director = User::factory()->create()->assignRole('Campus Director');
        $cycle = $this->cycle();
        $office = Office::factory()->create(['campus' => 6, 'is_active' => true]);
        $newOffice = Office::factory()->create(['campus' => 6, 'is_active' => true]);
        $excluded = Office::factory()->create(['campus' => 3, 'is_active' => true]);
        $head = User::factory()->create(['office_id' => $newOffice->id])->assignRole('Supervisor');
        $target = OfficeTarget::create(['created_by' => $director->id, 'evaluation_cycle_id' => $cycle->id, 'title' => 'Training', 'success_indicator' => 'Four workshops',
            'target_value' => 4, 'unit_of_measure' => 'workshops', 'weight' => 20, 'deadline' => '2026-12-15', 'priority' => 'medium']);
        $target->offices()->attach($office->id, ['allocated_target_value' => 4]);
        Http::fake(['api.openai.com/*' => Http::response($this->response(['briefings' => [['office_id' => $newOffice->id, 'wording' => 'Deliver six workshops for your department by December 20, 2026.',
            'summary' => 'Department training.', 'recommendation' => 'delegate', 'reason' => 'Delegate delivery if staff duties and capacity permit.']]]))]);
        $this->actingAs($director)->get(route('office-targets.edit', $target))->assertOk()->assertViewHas('offices', fn ($offices) => ! $offices->contains('id', $excluded->id));
        $data = ['evaluation_cycle_id' => $cycle->id, 'title' => 'Reviewed training', 'success_indicator' => 'Six workshops', 'target_value' => 6, 'unit_of_measure' => 'workshops',
            'weight' => 30, 'deadline' => '2026-12-20', 'priority' => 'high', 'office_ids' => [$newOffice->id], 'office_allocations' => [$newOffice->id => 6]];
        $this->put(route('office-targets.update', $target), $data)->assertSessionHasNoErrors()->assertRedirect();
        $audit = WorkflowAudit::sole();
        $this->assertSame($director->id, $audit->actor_id);
        $this->assertEquals(20, $audit->before['target']['weight']);
        $this->assertEquals(30, $audit->after['target']['weight']);
        $this->assertSame($office->id, $audit->before['offices'][0]['office_id']);
        $this->assertSame($newOffice->id, $audit->after['offices'][0]['office_id']);
        $this->assertEquals(100, $audit->after['offices'][0]['allocation_percentage']);
        $notice = $head->notifications()->sole()->data;
        $this->assertSame('delegate', $notice['recommendation']);
        $this->assertStringContainsString('six workshops', $notice['message']);
        $this->get(route('office-targets.show', $target))->assertOk()->assertSee('Target change audit log')->assertSee('Before');
        $this->put(route('office-targets.update', $target), [...$data, 'deadline' => '2027-01-01'])->assertSessionHasErrors('deadline');
        $this->put(route('office-targets.update', $target), [...$data, 'office_ids' => [$excluded->id], 'office_allocations' => [$excluded->id => 6]])->assertSessionHasErrors('office_ids.0');
        Http::assertSentCount(1);
    }
}
