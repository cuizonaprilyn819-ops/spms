<?php

namespace Tests\Feature;

use App\Models\{EvaluationCycle, Office, OfficeTarget, TargetDocument, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class TargetDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_duration_comes_from_each_semester_without_a_manual_deadline(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create()->assignRole('Planning Officer');
        $office = Office::factory()->create(['campus' => config('hinunangan.campus_id'), 'is_active' => true]);
        $this->mock(\App\Services\TargetDocumentAi::class, fn ($mock) => $mock->shouldReceive('briefings')->twice()->andReturn([$office->id => ['wording' => 'Prepare one report', 'summary' => 'Reporting', 'recommendation' => 'delegate', 'reason' => 'Reporting responsibility']]));
        $this->actingAs($owner);
        foreach ([EvaluationCycle::FIRST_SEMESTER, EvaluationCycle::SECOND_SEMESTER] as $semester) {
            $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => $semester, 'status' => 'open']);
            $document = TargetDocument::create(['created_by' => $owner->id, 'evaluation_cycle_id' => $cycle->id, 'original_name' => 'source.pdf', 'path' => 'source.pdf', 'mime_type' => 'application/pdf', 'status' => 'review']);
            $proposal = ['title' => 'Reporting', 'success_indicator' => 'One report', 'target_value' => 1, 'unit_of_measure' => 'reports', 'weight' => 10, 'deadline' => null, 'source_excerpt' => 'Prepare one report', 'classification_reason' => 'Source indicator', 'cd_only' => false, 'suggested_offices' => []];
            $draft = $document->drafts()->create(['proposal' => $proposal]);
            $this->get(route('target-drafts.review', $draft))->assertOk()->assertDontSee('id="review-deadline"', false)->assertSee('Target duration follows this period automatically.');
            $data = ['title' => 'Reporting', 'success_indicator' => 'One report', 'target_value' => 1, 'unit_of_measure' => 'reports', 'weight' => 10, 'priority' => 'medium', 'office_ids' => [$office->id], 'allocations' => [$office->id => 1], 'confirmed' => 1];
            // Neither missing dates nor client-supplied dates control the semester duration.
            if ($semester === EvaluationCycle::SECOND_SEMESTER) $data['deadline'] = '2030-01-01';
            $this->post(route('target-drafts.assign', $draft), $data)->assertSessionHasNoErrors()->assertRedirect();
            $target = OfficeTarget::findOrFail($draft->fresh()->office_target_id);
            $this->assertSame($cycle->periodStart()->toDateString(), $target->start_date->toDateString());
            $this->assertSame($cycle->periodEnd()->toDateString(), $target->deadline->toDateString());
        }
    }

    public function test_only_uploader_can_delete_document_and_drafts_without_deleting_assigned_targets(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $owner = User::factory()->create();
        $owner->assignRole('Planning Officer');
        $other = User::factory()->create();
        $other->assignRole('Planning Officer');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open']);
        Storage::disk('local')->put('wrong.pdf', 'wrong file');
        $document = TargetDocument::create(['created_by' => $owner->id, 'evaluation_cycle_id' => $cycle->id, 'original_name' => 'wrong.pdf', 'path' => 'wrong.pdf', 'mime_type' => 'application/pdf', 'status' => 'analyzing']);
        $target = OfficeTarget::create(['title' => 'Assigned target', 'success_indicator' => 'One report', 'target_value' => 1, 'unit_of_measure' => 'reports', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $owner->id]);
        $draft = $document->drafts()->create(['proposal' => ['title' => 'Draft', 'success_indicator' => 'One report', 'source_excerpt' => 'Submit one report.'], 'office_target_id' => $target->id]);
        $url = route('target-documents.destroy', $document);
        $this->actingAs($other)->delete($url)->assertForbidden();
        Storage::disk('local')->assertExists('wrong.pdf');
        $this->actingAs($owner)->delete($url)->assertSessionHasErrors('document');
        $this->assertModelExists($document);
        $document->update(['status' => 'review']);
        $this->get(route('target-documents.show', $document))->assertOk()->assertSee('Delete document');
        $this->delete($url)->assertRedirect(route('target-documents.index'));
        Storage::disk('local')->assertMissing('wrong.pdf');
        $this->assertModelMissing($document);
        $this->assertModelMissing($draft);
        $this->assertModelExists($target);
        $this->get(route('target-documents.index'))->assertOk()->assertSee('Document deleted.');
    }

    public function test_failed_analysis_can_be_retried_without_creating_assignments(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test-key', 'spms.ai.model' => 'test-model']);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
        Storage::disk('local')->put('example.pdf', 'synthetic');
        $document = TargetDocument::create(['created_by' => $director->id, 'evaluation_cycle_id' => $cycle->id, 'original_name' => 'example.pdf', 'path' => 'example.pdf', 'mime_type' => 'application/pdf']);
        Http::fakeSequence()->push(['error' => 'provider failure'], 503)->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['summary' => 'No targets', 'warnings' => 'No measurable targets found.', 'targets' => []])]]]]]);
        $this->actingAs($director)->post(route('target-documents.analyze', $document))->assertRedirect();
        $this->assertSame('failed', $document->fresh()->status);
        $this->assertSame(0, OfficeTarget::count());
        $this->post(route('target-documents.analyze', $document))->assertRedirect();
        $this->assertSame('review', $document->fresh()->status);
        $this->assertSame(0, $document->drafts()->count());
        $this->post(route('target-documents.analyze', $document))->assertSessionHasErrors('analysis');
        Http::assertSentCount(2);
    }

    public function test_upload_analysis_review_assignment_and_duplicate_submission(): void
    {
        $this->seed(RoleSeeder::class);
        $storage = Storage::fake('local');
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test-key', 'spms.ai.model' => 'test-model']);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $office = Office::factory()->create(['is_active' => true, 'campus' => config('hinunangan.campus_id')]);
        $inactiveOffice = Office::factory()->create(['is_active' => false, 'campus' => config('hinunangan.campus_id')]);
        $otherCampusOffice = Office::factory()->create(['is_active' => true, 'campus' => 2]);
        $supervisor = User::factory()->create(['office_id' => $office->id]);
        $supervisor->assignRole('Supervisor');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
        $proposal = ['program' => 'Reporting', 'program_outcome' => 'Timely institutional reports', 'title' => 'Reports', 'success_indicator' => 'Four submitted reports', 'target_value' => 4, 'unit_of_measure' => 'reports', 'weight' => 20, 'weight_reason' => 'Source weight', 'deadline' => '2026-12-31', 'source_excerpt' => 'Submit four reports.', 'cd_only' => false, 'classification_reason' => 'Office reporting', 'suggested_offices' => [['office_id' => $office->id, 'reason' => 'Reporting responsibility', 'allocation_percentage' => 100, 'allocated_target_value' => 4]]];
        $response = fn ($data) => ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
        Http::fakeSequence()->push($response(['summary' => 'Reporting targets', 'warnings' => '', 'targets' => [$proposal, $proposal, [...$proposal, 'cd_only' => true]]]))
            ->push($response(['briefings' => [['office_id' => $office->id, 'wording' => 'Prepare four office reports.', 'summary' => 'Office reporting', 'recommendation' => 'delegate', 'reason' => 'Delegate if staff capacity permits.']]]));
        $this->actingAs($director)->post(route('target-documents.store'), ['evaluation_cycle_id' => $cycle->id, 'file' => UploadedFile::fake()->create('main-campus.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors()->assertRedirect();
        $document = TargetDocument::sole();
        $storage->assertExists($document->path);
        $this->post(route('target-documents.analyze', $document))->assertRedirect();
        $this->assertSame('review', $document->fresh()->status);
        $this->get(route('target-documents.show', $document))->assertOk();
        $this->assertSame(2, $document->drafts()->count());
        $draft = $document->drafts()->first();
        $this->get(route('target-drafts.review', $draft))->assertOk()
            ->assertViewHas('offices', fn ($offices) => $offices->modelKeys() === [$office->id]);
        $cdOnly = $document->drafts()->latest('id')->first();
        $this->assertSame([], $cdOnly->proposal['suggested_offices']);
        $this->post(route('target-drafts.assign', $cdOnly), [])->assertStatus(422);
        $data = ['title' => 'Reviewed reports', 'success_indicator' => 'Four reports', 'target_value' => 4, 'unit_of_measure' => 'reports', 'weight' => 20, 'deadline' => '2026-12-31', 'priority' => 'medium', 'office_ids' => [$office->id], 'allocations' => [$office->id => 4], 'confirmed' => 1];
        foreach ([$inactiveOffice, $otherCampusOffice] as $excludedOffice) {
            $this->post(route('target-drafts.assign', $draft), [...$data, 'office_ids' => [$excludedOffice->id], 'allocations' => [$excludedOffice->id => 4]])
                ->assertSessionHasErrors('office_ids.0');
        }
        $this->post(route('target-drafts.assign', $draft), [...$data, 'allocations' => [$office->id => 3]])->assertSessionHasErrors('allocations');
        $this->post(route('target-drafts.assign', $draft), $data)->assertSessionHasNoErrors()->assertRedirect();
        $target = OfficeTarget::sole();
        $this->assertSame('Reviewed reports', $target->title);
        $this->assertStringContainsString('Prepare four office reports.', $supervisor->notifications()->sole()->data['message']);
        $this->get(route('office-targets.show', $target))->assertOk()->assertSee('Delegate if staff capacity permits.');
        $this->post(route('target-drafts.assign', $draft), $data)->assertRedirect();
        $this->assertSame(1, OfficeTarget::count());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['store'] === false && str_contains(json_encode($request['input']), 'JSON'));
        $other = User::factory()->create();
        $other->assignRole('Campus Director');
        $this->actingAs($other)->get(route('target-documents.download', $document))->assertForbidden();
        $this->post(route('target-drafts.assign', $draft), $data)->assertForbidden();
        $this->actingAs($supervisor)->get(route('target-documents.index'))->assertForbidden();
    }
}
