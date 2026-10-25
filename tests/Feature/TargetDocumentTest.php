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

    public function test_failed_analysis_can_be_retried_without_creating_assignments(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test-key', 'spms.ai.model' => 'test-model']);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
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
        Storage::fake('local');
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test-key', 'spms.ai.model' => 'test-model']);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $office = Office::factory()->create(['is_active' => true, 'campus' => config('hinunangan.campus_id')]);
        $inactiveOffice = Office::factory()->create(['is_active' => false, 'campus' => config('hinunangan.campus_id')]);
        $otherCampusOffice = Office::factory()->create(['is_active' => true, 'campus' => 2]);
        $supervisor = User::factory()->create(['office_id' => $office->id]);
        $supervisor->assignRole('Supervisor');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
        $proposal = ['title' => 'Reports', 'success_indicator' => 'Four submitted reports', 'target_value' => 4, 'unit_of_measure' => 'reports', 'weight' => 20, 'deadline' => '2026-12-31', 'source_excerpt' => 'Submit four reports.', 'cd_only' => false, 'classification_reason' => 'Office reporting', 'suggested_offices' => [['office_id' => $office->id, 'reason' => 'Reporting responsibility']]];
        $response = fn ($data) => ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
        Http::fakeSequence()->push($response(['summary' => 'Reporting targets', 'warnings' => '', 'targets' => [$proposal, [...$proposal, 'cd_only' => true]]]))
            ->push($response(['briefings' => [['office_id' => $office->id, 'wording' => 'Prepare four office reports.', 'summary' => 'Office reporting', 'recommendation' => 'delegate', 'reason' => 'Delegate if staff capacity permits.']]]));
        $this->actingAs($director)->post(route('target-documents.store'), ['evaluation_cycle_id' => $cycle->id, 'file' => UploadedFile::fake()->create('main-campus.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors()->assertRedirect();
        $document = TargetDocument::sole();
        Storage::disk('local')->assertExists($document->path);
        $this->post(route('target-documents.analyze', $document))->assertRedirect();
        $this->assertSame('review', $document->fresh()->status);
        $this->get(route('target-documents.show', $document))->assertOk();
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
