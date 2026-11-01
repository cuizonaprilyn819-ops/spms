<?php

namespace Tests\Feature;

use App\Models\{EvaluationCycle, OfficeTarget, TargetDocument, User};
use App\Services\TargetDocumentAi;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class TargetHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function source(): TargetDocument
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Storage::disk('local')->put('scanned.pdf', 'synthetic scanned source');
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test', 'spms.ai.model' => 'test-model', 'spms.ai.document_model' => 'document-reader-model']);
        $owner = User::factory()->create()->assignRole('Planning Officer');
        $this->actingAs($owner);
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open']);
        return TargetDocument::create(['created_by' => $owner->id, 'evaluation_cycle_id' => $cycle->id, 'original_name' => 'scanned.pdf', 'path' => 'scanned.pdf', 'mime_type' => 'application/pdf']);
    }

    private function response(array $data): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
    }

    private function target(): array
    {
        return ['title' => 'Revise curriculum', 'success_indicator' => 'One curriculum revised', 'source_excerpt' => '1 curriculum revised and approved', 'target_value' => 1, 'unit_of_measure' => 'curricula', 'weight' => null, 'weight_reason' => null, 'deadline' => null, 'cd_only' => false, 'classification_reason' => 'Source indicator', 'suggested_offices' => []];
    }

    public function test_missing_hierarchy_triggers_focused_source_read_and_keeps_distinct_source_groups(): void
    {
        $document = $this->source();
        Http::fakeSequence()
            ->push($this->response(['summary' => 'Extracted source indicators', 'warnings' => '', 'targets' => [$this->target(), $this->target()]]))
            ->push($this->response(['warnings' => '', 'mappings' => [
                ['target_index' => 1, 'program' => 'Community education', 'program_outcome' => 'Accessible learning'],
                ['target_index' => 0, 'program' => 'Curricular reform', 'program_outcome' => 'Globally competitive graduates'],
            ]]));
        $this->post(route('target-documents.analyze', $document))->assertSessionHasNoErrors();
        $drafts = $document->drafts()->orderBy('id')->get();
        $this->assertCount(2, $drafts);
        $this->assertSame('Curricular reform', $drafts[0]->proposal['program']);
        $this->assertSame('Globally competitive graduates', $drafts[0]->proposal['program_outcome']);
        $this->assertSame('Community education', $drafts[1]->proposal['program']);
        $this->assertEquals(1, $drafts[0]->proposal['target_value']);
        $this->get(route('target-documents.show', $document))->assertOk()->assertSee('Curricular reform')->assertSee('Accessible learning');
        Http::assertSentCount(2);
    }

    public function test_existing_results_can_be_repaired_without_recreating_targets_or_changing_assignments(): void
    {
        $document = $this->source();
        $document->update(['status' => 'review']);
        $target = OfficeTarget::create(['title' => 'Keep assigned target', 'success_indicator' => 'One curriculum', 'target_value' => 1, 'weight' => 10, 'unit_of_measure' => 'curricula', 'priority' => 'medium', 'deadline' => '2026-12-31', 'created_by' => $document->created_by]);
        $draft = $document->drafts()->create(['proposal' => $this->target(), 'office_target_id' => $target->id]);
        Http::fakeSequence()->push($this->response(['warnings' => '', 'mappings' => [['target_index' => 0, 'program' => 'Curricular reform', 'program_outcome' => 'Competitive graduates']]]));
        $other = User::factory()->create()->assignRole('Planning Officer');
        $this->actingAs($other)->post(route('target-documents.hierarchy', $document))->assertForbidden();
        $this->actingAs(User::findOrFail($document->created_by))->post(route('target-documents.hierarchy', $document))->assertRedirect();
        $this->assertSame('review', $document->fresh()->status);
        $this->assertSame('Curricular reform', $draft->fresh()->proposal['program']);
        $this->assertSame($target->id, $draft->fresh()->office_target_id);
        $this->assertDatabaseCount('office_targets', 1);
        $this->assertDatabaseCount('target_document_drafts', 1);
        $this->get(route('target-documents.show', $document))->assertOk()->assertSee('Re-read programs and outcomes');
        Http::assertSentCount(1);
    }

    public function test_invalid_or_failed_hierarchy_read_preserves_previous_drafts(): void
    {
        $document = $this->source();
        $document->update(['status' => 'review']);
        $proposal = [...$this->target(), 'program' => 'Existing program', 'program_outcome' => 'Existing outcome'];
        $draft = $document->drafts()->create(['proposal' => $proposal]);
        Http::fakeSequence()->push($this->response(['warnings' => '', 'mappings' => [['target_index' => 99, 'program' => 'Incorrect', 'program_outcome' => 'Incorrect']]]));
        $this->post(route('target-documents.hierarchy', $document))->assertRedirect();
        $this->assertSame($proposal, $draft->fresh()->proposal);
        $this->assertSame('review', $document->fresh()->status);
        $this->assertStringContainsString('could not reliably match', $document->fresh()->error);
    }

    public function test_genuinely_blank_outcomes_remain_null_with_a_reading_note(): void
    {
        $document = $this->source();
        Http::fakeSequence()->push($this->response(['warnings' => 'Outcome cell is empty for target_index 0.', 'mappings' => [['target_index' => 0, 'program' => 'Faculty training', 'program_outcome' => null]]]));
        $result = app(TargetDocumentAi::class)->hierarchy($document, [$this->target()]);
        $this->assertNull($result['mappings'][0]['program_outcome']);
        $this->assertStringContainsString('Outcome cell is empty', $result['warnings']);
    }

    public function test_section_headers_and_copied_indicators_are_retried_instead_of_used_as_labels(): void
    {
        $document = $this->source();
        Http::fakeSequence()
            ->push($this->response(['warnings' => '', 'mappings' => [['target_index' => 0, 'program' => 'Programs', 'program_outcome' => 'One curriculum revised']]]))
            ->push($this->response(['warnings' => '', 'mappings' => [['target_index' => 0, 'program' => 'Curricular reform', 'program_outcome' => 'Competitive graduates']]]));
        $result = app(TargetDocumentAi::class)->hierarchy($document, [$this->target()]);
        $this->assertSame('Curricular reform', $result['mappings'][0]['program']);
        $this->assertSame('Competitive graduates', $result['mappings'][0]['program_outcome']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['input'][0]['content'][0]['detail'] === 'high' && $request['model'] === 'document-reader-model');
    }

    public function test_repeated_strategic_headings_are_flagged_rather_than_presented_as_a_program(): void
    {
        $document = $this->source();
        $wrong = $this->response(['warnings' => '', 'mappings' => [['target_index' => 0, 'program' => 'Strategic Goal 1: Education', 'program_outcome' => 'Strategic Objective 1: Quality']]]);
        Http::fakeSequence()->push($wrong)->push($wrong);
        $result = app(TargetDocumentAi::class)->hierarchy($document, [$this->target()]);
        $this->assertNull($result['mappings'][0]['program']);
        $this->assertNull($result['mappings'][0]['program_outcome']);
        $this->assertStringContainsString('rejected', $result['warnings']);
    }

    public function test_multi_page_sources_can_keep_more_than_forty_distinct_indicators(): void
    {
        $document = $this->source();
        $targets = [];
        for ($index = 1; $index <= 41; $index++) {
            $targets[] = [...$this->target(), 'title' => 'Indicator '.$index, 'success_indicator' => 'Deliver output '.$index, 'source_excerpt' => 'Deliver output '.$index, 'program' => 'Curricular reform', 'program_outcome' => 'Competitive graduates'];
        }
        Http::fakeSequence()->push($this->response(['summary' => 'All source rows', 'warnings' => '', 'targets' => $targets]));
        $result = app(TargetDocumentAi::class)->analyze($document);
        $this->assertCount(41, $result['targets']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['max_output_tokens'] === 32000);
    }
}
