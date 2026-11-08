<?php

namespace Tests\Feature;

use App\Jobs\ProcessMov;
use App\Models\{Ipcr, Mov, Office, PerformanceTarget, User};
use App\Services\{EvidenceFiles, EvidenceOcr, EvidenceProcessing, MovQuestions, ReportEvidence};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Artisan, DB, Http, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class MovBackgroundProcessingTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        config(['spms.mov.connection' => 'mov-processing', 'spms.ai.enabled' => true, 'spms.ai.api_key' => 'test', 'spms.ai.model' => 'test', 'spms.target_assistance.enabled' => false]);
        $user = User::factory()->create(['office_id' => Office::factory()->create()->id])->assignRole('Employee');
        $report = Ipcr::factoryLike()->create(['employee' => $user, 'prepared_by_user' => $user]);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $report]);
        $target->update(['quantity' => 2, 'unit' => 'articles']);
        $this->actingAs($user);
        $token = (string) Str::uuid();
        $reference = 'report:'.$target->id;
        $this->withSession(['mov_questions' => [$token => ['user_id' => $user->id, 'target_ref' => $reference,
            'target_snapshot' => app(MovQuestions::class)->snapshot($user, $reference), 'expires_at' => now()->addHour()->timestamp,
            'questions' => [['id' => 'q1', 'label' => 'How many articles?', 'type' => 'number', 'required' => true]],
            'guidance' => 'Attach the dated articles.',
        ]]]);
        return [$user, $report, $target, ['target_ref' => $reference, 'questionnaire_token' => $token, 'title' => 'Synthetic article evidence', 'answers' => ['q1' => 2]]];
    }

    private function findings(bool $satisfied = true, bool $unreadable = false): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
            'summary' => 'Synthetic analysis.', 'user_data_summary' => 'The user reports 2 articles.', 'file_findings_summary' => 'Synthetic dated records.',
            'discrepancies' => '', 'supported' => true, 'satisfies_target' => $satisfied,
            'target_assessment' => $unreadable ? 'Small text is unreadable.' : ($satisfied ? 'The assigned quantity is evidenced.' : 'Only one of the two articles is evidenced.'),
            'statement' => 'Submitted 2 articles.', 'source_excerpts' => ['proof.png: synthetic article record.'], 'limitations' => $unreadable ? 'Unreadable text.' : '',
        ])]]]]];
    }

    private function upload(array $data, int $count = 1): void
    {
        $files = [];
        for ($i = 0; $i < $count; $i++) $files[] = UploadedFile::fake()->image('proof'.$i.'.png', 2400, 1200);
        $this->post(route('movs.store'), $data + ['files' => $files])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function work(): void
    {
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'mov-processing', '--queue' => 'mov-processing', '--once' => true, '--tries' => 1]));
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_upload_returns_before_ai_and_one_queued_job_processes_the_batch_with_private_previews(): void
    {
        [$user, $report, , $data] = $this->fixture();
        Http::fake(['*' => Http::response($this->findings())]);
        $this->upload($data, 2);
        Http::assertNothingSent();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('mov-processing', DB::table('jobs')->sole()->queue);
        $movs = Mov::all();
        $hashes = $movs->mapWithKeys(fn ($mov) => [$mov->id => hash('sha256', Storage::disk('public')->get($mov->file_path))]);
        foreach ($movs as $mov) $this->assertSame('queued', $mov->analysis->status);
        $this->get(route('movs.show', $movs->first()))->assertOk()->assertSee('Processing MOV');
        $this->getJson(route('evidence.status', ['mov', $movs->first()]))->assertOk()->assertJsonPath('status', 'queued');
        $this->assertNotEmpty(app(ReportEvidence::class)->issues($report));
        $this->patch(route('report-workflow.submit', ['ipcr', $report]))->assertSessionHasErrors('mov');
        $this->post(route('evidence.analyze', ['mov', $movs->last()]))->assertRedirect();
        $this->assertDatabaseCount('jobs', 1);
        $this->work();
        Http::assertSentCount(1);
        foreach ($movs as $mov) {
            $analysis = $mov->fresh()->analysis;
            $this->assertSame('completed', $analysis->status);
            $this->assertCount(2, $analysis->findings['batch_evidence_ids']);
            $this->assertArrayHasKey('ai_ms', $analysis->findings['processing']);
            $preview = app(EvidenceFiles::class)->previewPath($mov);
            $this->assertNotNull($preview, json_encode(['gd' => function_exists('imagewebp'), 'local_files' => Storage::disk('local')->allFiles(), 'original' => $mov->original_name]));
            $this->assertSame('image/webp', Storage::disk('local')->mimeType($preview));
            $this->assertLessThan(Storage::disk('public')->size($mov->file_path), Storage::disk('local')->size($preview));
            $this->assertSame($hashes[$mov->id], hash('sha256', Storage::disk('public')->get($mov->file_path)));
        }
        $this->assertSame([], app(ReportEvidence::class)->issues($report->fresh()));
        $first = $movs->first();
        $this->get(route('movs.preview', $first))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('movs.download', $first))->assertDownload($first->original_name);
        $this->getJson(route('evidence.status', ['mov', $first]))->assertOk()->assertJsonPath('status', 'completed')->assertJsonStructure(['html']);
        $outsider = User::factory()->create()->assignRole('Employee');
        $this->actingAs($outsider)->get(route('movs.preview', $first))->assertForbidden();
        $this->getJson(route('evidence.status', ['mov', $first]))->assertForbidden();
    }

    public function test_unreadable_text_uses_ocr_once_and_keeps_original_images_in_the_followup_request(): void
    {
        [, $report, , $data] = $this->fixture();
        Http::fakeSequence()->push($this->findings(false, true))->push($this->findings());
        $this->partialMock(EvidenceOcr::class, fn ($mock) => $mock->shouldReceive('extract')->once()->andReturnUsing(function ($file) use ($report) {
            $analysis = $file->fresh()->analysis;
            $this->assertSame('completed', $analysis->status);
            $analysis->update(['findings' => [...$analysis->findings, 'satisfies_target' => true]]);
            $this->assertFalse($analysis->isReady());
            $this->assertNotEmpty(app(ReportEvidence::class)->issues($report));
            $this->getJson(route('evidence.status', ['mov', $file]))->assertOk()->assertJsonPath('status', 'analyzing')->assertJsonPath('html', null);
            return 'Synthetic OCR: 2 dated articles.';
        }));
        $this->upload($data);
        $this->work();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains(json_encode($request['input']), 'Synthetic OCR')
            && collect($request['input'][0]['content'])->contains(fn ($part) => str_starts_with($part['image_url'] ?? '', 'data:image/png;base64,')));
        $this->assertTrue(Mov::sole()->analysis->findings['processing']['ocr_used']);
    }

    public function test_ordinary_partial_evidence_does_not_trigger_ocr_or_an_extra_ai_request(): void
    {
        [, $report, , $data] = $this->fixture();
        Http::fake(['*' => Http::response($this->findings(false))]);
        $this->partialMock(EvidenceOcr::class, fn ($mock) => $mock->shouldNotReceive('extract'));
        $this->upload($data);
        $this->work();
        Http::assertSentCount(1);
        $this->assertNotEmpty(app(ReportEvidence::class)->issues($report));
        $this->assertFalse(Mov::sole()->analysis->findings['processing']['ocr_used']);
    }

    public function test_missing_ocr_and_worker_failure_preserve_files_and_allow_retry(): void
    {
        [, $report, , $data] = $this->fixture();
        config(['spms.mov.ocr_binary' => base_path('tmp/no-ocr-installed.exe')]);
        Http::fake(['*' => Http::response($this->findings(false, true))]);
        $this->upload($data);
        $this->work();
        Http::assertSentCount(1);
        $file = Mov::sole();
        $this->assertFalse($file->analysis->findings['processing']['ocr_used']);
        app(EvidenceProcessing::class)->queue($file);
        (new ProcessMov('mov', $file->id))->failed(new \RuntimeException('Synthetic worker outage'));
        $this->assertSame('failed', $file->fresh()->analysis->status);
        Storage::disk('public')->assertExists($file->file_path);
        $this->assertNotEmpty(app(ReportEvidence::class)->issues($report));
    }
}
