<?php

namespace Tests\Feature;

use App\Models\{Ipcr, Office, OfficeTarget, TargetMov, User};
use App\Services\ReportEvidence;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class MovQuestionnaireWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test', 'spms.ai.model' => 'test']);
        $user = User::factory()->create(['office_id' => Office::factory()->create()->id])->assignRole('Employee');
        $target = OfficeTarget::create(['title' => 'University articles', 'success_indicator' => '44 articles submitted to the university platform', 'target_value' => 44, 'unit_of_measure' => 'articles', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $user->id]);
        $target->offices()->attach($user->office_id, ['allocated_target_value' => 44]);
        $target->assignedEmployees()->attach($user, ['accepted_at' => now(), 'allocated_target_value' => 2, 'assigned_by' => $user->id]);
        $this->actingAs($user);
        return [$user, $target];
    }

    private function response(array $data): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
    }

    private function questions(): array
    {
        return ['questions' => [['id' => 'q1', 'label' => 'How many articles did you submit?', 'type' => 'number', 'required' => true], ['id' => 'q2', 'label' => 'Which platform published the articles?', 'type' => 'text', 'required' => true]], 'guidance' => 'Attach published articles showing the platform and dates.'];
    }

    private function findings(bool $satisfied): array
    {
        return ['summary' => $satisfied ? 'Two articles are evidenced.' : 'The files do not establish two published articles.', 'user_data_summary' => 'The user reports two university articles.', 'file_findings_summary' => $satisfied ? 'Two distinct dated articles are visible.' : 'Only one article is visible; the other file is unrelated.', 'discrepancies' => $satisfied ? '' : 'The claimed second article is not evidenced.', 'supported' => true, 'satisfies_target' => $satisfied, 'target_assessment' => $satisfied ? 'The two-article allocation is satisfied.' : 'The confirmed allocation requires two articles.', 'statement' => $satisfied ? 'Submitted 2 articles to the university platform.' : 'Submitted 1 evidenced article.', 'source_excerpts' => ['article1.pdf page 1: publication record.', 'article2.pdf page 1: supporting record.'], 'limitations' => ''];
    }

    public function test_questions_answers_and_multiple_files_are_analyzed_together_and_summarized_separately(): void
    {
        [$user, $target] = $this->fixture();
        Http::fakeSequence()->push($this->response($this->questions()))->push($this->response($this->findings(true)));
        $start = $this->post(route('movs.questions'), ['target_ref' => 'office:'.$target->id])->assertSessionHasNoErrors()->assertRedirect();
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->get($start->headers->get('Location'))->assertOk()->assertSee('How many articles')->assertSee('Which platform')->assertSee('name="files[]"', false);
        $data = ['target_ref' => 'office:'.$target->id, 'questionnaire_token' => $query['questionnaire_token'], 'title' => 'Article submissions', 'completed_value' => 2];
        $this->post(route('movs.store'), [...$data, 'answers' => ['q1' => 2], 'files' => [UploadedFile::fake()->createWithContent('article1.pdf', '%PDF-1.4 first record')]])->assertSessionHasErrors('answers.q2');
        $this->assertDatabaseCount('target_movs', 0);
        $this->post(route('movs.store'), [...$data, 'answers' => ['q1' => 2, 'q2' => 'University platform'], 'files' => [UploadedFile::fake()->createWithContent('article1.pdf', '%PDF-1.4 first record'), UploadedFile::fake()->createWithContent('article2.pdf', '%PDF-1.4 second record')]])->assertSessionHasNoErrors()->assertRedirect();
        $files = TargetMov::with('analysis')->get();
        $this->assertCount(2, $files);
        foreach ($files as $file) {
            $this->assertSame('completed', $file->analysis->status);
            $this->assertTrue($file->analysis->findings['satisfies_target']);
            $this->assertCount(2, $file->analysis->findings['batch_evidence_ids']);
            $this->assertEquals(2, $file->submission_context['answers']['q1']);
        }
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request['instructions'], 'Inspect ALL') && count(array_filter($request['input'][0]['content'], fn ($c) => $c['type'] === 'input_file')) === 2 && str_contains(json_encode($request['input']), 'University platform'));
        $this->get(route('office-targets.show', $target))->assertOk()->assertSee('User-provided data')->assertSee('Findings from uploaded files')->assertSee('Your submitted answers');
        $report = Ipcr::create(['office_target_id' => $target->id, 'employee_id' => $user->id, 'prepared_by' => $user->id, 'title' => 'Articles IPCR', 'status' => 'draft']);
        $this->assertSame([], app(ReportEvidence::class)->issues($report));
        Storage::disk('local')->delete($files->last()->path);
        $this->assertNotEmpty(app(ReportEvidence::class)->issues($report->fresh()));
    }

    public function test_missing_questionnaire_changed_allocation_and_unrelated_evidence_do_not_pass(): void
    {
        [$user, $target] = $this->fixture();
        Http::fakeSequence()->push($this->response($this->questions()))->push($this->response($this->findings(false)));
        $base = ['target_ref' => 'office:'.$target->id, 'title' => 'Article files', 'completed_value' => 2, 'answers' => ['q1' => 2, 'q2' => 'University platform']];
        $this->post(route('movs.store'), [...$base, 'files' => [UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 record')]])->assertSessionHasErrors('questionnaire_token');
        $start = $this->post(route('movs.questions'), ['target_ref' => 'office:'.$target->id]);
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $target->assignedEmployees()->updateExistingPivot($user->id, ['allocated_target_value' => 3]);
        $this->post(route('movs.store'), [...$base, ...$query, 'files' => [UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 record')]])->assertSessionHasErrors('questions');
        $target->assignedEmployees()->updateExistingPivot($user->id, ['allocated_target_value' => 2]);
        $this->post(route('movs.store'), [...$base, ...$query, 'files' => [UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 unrelated record')]])->assertSessionHasNoErrors();
        $this->assertFalse(TargetMov::sole()->analysis->findings['satisfies_target']);
        $report = Ipcr::create(['office_target_id' => $target->id, 'employee_id' => $user->id, 'prepared_by' => $user->id, 'title' => 'IPCR', 'status' => 'draft']);
        $this->patch(route('report-workflow.submit', ['ipcr', $report]))->assertSessionHasErrors('mov');
        $this->assertSame('draft', $report->fresh()->status);
    }

    public function test_report_mov_answers_are_private_and_expired_questions_restart_the_flow(): void
    {
        [$user] = $this->fixture();
        Storage::fake('public');
        $report = Ipcr::factoryLike()->create(['employee' => $user, 'prepared_by_user' => $user]);
        $target = \App\Models\PerformanceTarget::factoryLike()->create(['targetable' => $report]);
        $target->update(['quantity' => 2, 'unit' => 'articles']);
        Http::fakeSequence()->push($this->response($this->questions()))->push($this->response($this->findings(true)));
        $start = $this->post(route('movs.questions'), ['target_ref' => 'report:'.$target->id])->assertSessionHasNoErrors();
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->post(route('movs.store'), ['target_ref' => 'report:'.$target->id, ...$query, 'answers' => ['q1' => 2, 'q2' => 'University platform'], 'title' => 'Private evidence answers', 'files' => [UploadedFile::fake()->createWithContent('articles.pdf', '%PDF-1.4 two records')]])->assertSessionHasNoErrors()->assertRedirect();
        $mov = \App\Models\Mov::sole();
        $this->assertSame('completed', $mov->analysis->status);
        $this->get(route('movs.show', $mov))->assertOk()->assertSee('University platform')->assertSee('User-provided data');
        $other = User::factory()->create()->assignRole('Employee');
        $this->actingAs($other)->get(route('movs.show', $mov))->assertForbidden();
        $this->get(route('movs.download', $mov))->assertForbidden();
        $this->post(route('movs.questions'), ['target_ref' => 'report:'.$target->id])->assertForbidden();
        $this->actingAs($user);
        \Illuminate\Support\Carbon::withTestNow(now()->addHours(3), function () use ($query) {
            $this->get(route('movs.create', $query))->assertRedirect(route('movs.create'))->assertSessionHasErrors('questions');
        });
        Http::assertSentCount(2);
    }
}
