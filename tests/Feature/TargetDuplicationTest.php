<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\OfficeTarget;
use App\Models\PerformanceTarget;
use App\Models\TargetDocument;
use App\Models\User;
use App\Services\TargetDocumentAi;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TargetDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $planner;

    private EvaluationCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $this->planner = User::factory()->create()->assignRole('Planning Officer');
        $this->cycle = EvaluationCycle::create(['year' => 2028, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
    }

    private function upload(User $owner, EvaluationCycle $cycle, string $name, string $content): void
    {
        $this->actingAs($owner)->post(route('target-documents.store'), [
            'evaluation_cycle_id' => $cycle->id,
            'file' => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".$content),
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_identical_reuploads_reuse_the_document_and_remove_the_unused_file(): void
    {
        $this->upload($this->planner, $this->cycle, 'original.pdf', 'Same source');
        $document = TargetDocument::sole();
        $this->upload($this->planner, $this->cycle, 'renamed.pdf', 'Same source');
        $this->assertDatabaseCount('target_documents', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('target-documents'));
        $this->assertSame($document->path, TargetDocument::sole()->path);
        $this->assertSame(hash('sha256', "%PDF-1.4\nSame source"), $document->content_hash);
    }

    public function test_changed_content_different_owners_and_different_periods_remain_separate(): void
    {
        $this->upload($this->planner, $this->cycle, 'source.pdf', 'Same source');
        $this->upload($this->planner, $this->cycle, 'source.pdf', 'Revised source');
        $other = User::factory()->create()->assignRole('Planning Officer');
        $this->upload($other, $this->cycle, 'source.pdf', 'Same source');
        $nextCycle = EvaluationCycle::create(['year' => 2029, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
        $this->upload($this->planner, $nextCycle, 'source.pdf', 'Same source');
        $this->assertDatabaseCount('target_documents', 4);
        $this->assertCount(4, Storage::disk('local')->allFiles('target-documents'));
    }

    public function test_superseded_analysis_cannot_append_drafts_or_overwrite_newer_results(): void
    {
        $this->upload($this->planner, $this->cycle, 'source.pdf', 'Source');
        $document = TargetDocument::sole();
        $this->mock(TargetDocumentAi::class, function ($mock) use ($document) {
            $mock->shouldReceive('analyze')->once()->andReturnUsing(function () use ($document) {
                // A newer request completed after reclaiming this stale analysis.
                TargetDocument::whereKey($document->id)->update(['status' => 'review', 'analysis_token' => null, 'summary' => 'Newer result']);
                $document->drafts()->create(['proposal' => ['title' => 'Newer target']]);

                return ['targets' => [['title' => 'Stale target']], 'summary' => 'Stale result'];
            });
        });
        $this->actingAs($this->planner)->post(route('target-documents.analyze', $document))->assertRedirect();
        $this->assertSame('Newer result', $document->fresh()->summary);
        $this->assertSame('Newer target', $document->drafts()->sole()->proposal['title']);
    }

    public function test_superseded_analysis_failure_cannot_mark_newer_results_failed(): void
    {
        $this->upload($this->planner, $this->cycle, 'source.pdf', 'Source');
        $document = TargetDocument::sole();
        $this->mock(TargetDocumentAi::class, function ($mock) use ($document) {
            $mock->shouldReceive('analyze')->once()->andReturnUsing(function () use ($document) {
                TargetDocument::whereKey($document->id)->update(['status' => 'review', 'analysis_token' => null, 'summary' => 'Newer result']);
                throw new \RuntimeException('Stale provider failure');
            });
        });
        $this->actingAs($this->planner)->post(route('target-documents.analyze', $document))->assertRedirect();
        $this->assertSame('review', $document->fresh()->status);
        $this->assertNull($document->fresh()->error);
    }

    public function test_planning_projections_do_not_duplicate_report_targets_in_the_list(): void
    {
        $this->planner->assignRole('Administrator');
        $source = PerformanceTarget::factoryLike()->create();
        $projection = new OfficeTarget;
        $projection->forceFill([
            'performance_target_id' => $source->id, 'evaluation_cycle_id' => $this->cycle->id,
            'title' => $source->target, 'success_indicator' => $source->success_indicator,
            'target_value' => 100, 'unit_of_measure' => 'requests', 'weight' => 25,
            'deadline' => '2028-06-30', 'created_by' => $this->planner->id,
        ])->save();
        $legacy = $projection->replicate();
        $legacy->performance_target_id = null;
        $legacy->title = 'Separate legacy target';
        $legacy->save();
        $this->actingAs($this->planner)->get(route('performance-targets.index'))
            ->assertOk()
            ->assertViewHas('officeTargets', fn ($targets) => $targets->total() === 1 && $targets->first()->is($legacy))
            ->assertViewHas('targets', fn ($targets) => $targets->contains('id', $source->id));
    }
}
