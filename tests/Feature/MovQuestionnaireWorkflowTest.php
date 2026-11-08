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

    public function test_question_failure_is_visible_to_browser_api_and_native_page(): void
    {
        [, $target] = $this->fixture();
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Unavailable']], 503)]);
        $this->postJson('/api/movs/questions', ['target_ref' => 'office:'.$target->id])
            ->assertUnprocessable()->assertJsonValidationErrors('questions');
        $this->from(route('office-targets.show', $target))
            ->post(route('movs.questions'), ['target_ref' => 'office:'.$target->id])
            ->assertRedirect(route('office-targets.show', $target))->assertSessionHasErrors('questions');
        $this->get(route('office-targets.show', $target))->assertOk()
            ->assertSee('AI could not prepare the questions. Please retry.');
    }

    public function test_report_action_is_outside_mov_and_questions_open_through_browser_api(): void
    {
        [, $target] = $this->fixture();
        $page = $this->get(route('office-targets.show', $target))->assertOk()->getContent();
        $start = strpos($page, '<section id="target-movs"');
        $mov = substr($page, $start, strpos($page, '</section>', $start) - $start);
        $this->assertStringNotContainsString('Prepare My IPCR Report', $mov);
        $this->assertStringContainsString('Upload MOV files', $mov);
        $this->assertStringContainsString('Prepare My IPCR Report', substr($page, strpos($page, '</section>', $start)));
        Http::fakeSequence()->push($this->response([...$this->questions(), 'mov_title' => 'Article publication evidence', 'mov_description' => 'Supporting files for the assigned university articles.']));
        $response = $this->postJson('/api/movs/questions', ['target_ref' => 'office:'.$target->id])
            ->assertOk()->assertJsonStructure(['redirect']);
        Http::assertSent(function ($request) {
            // JSON mode checks input messages, not only the top-level instructions.
            $this->assertSame('json_object', $request['text']['format']['type']);
            $this->assertSame('developer', $request['input'][0]['role']);
            $this->assertStringContainsString('JSON', $request['input'][0]['content'][0]['text']);
            $this->assertSame('user', $request['input'][1]['role']);
            $this->assertIsArray(json_decode($request['input'][1]['content'][0]['text'], true));
            return true;
        });
        $this->get($response->json('redirect'))->assertOk()->assertSee('How many articles')->assertSee('name="files[]"', false)
            ->assertSee('Article publication evidence')->assertSee('Supporting files for the assigned university articles.');
    }

    public function test_exhausted_ai_quota_explains_why_upload_questions_cannot_start(): void
    {
        [, $target] = $this->fixture();
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['code' => 'insufficient_quota', 'message' => 'Private provider details']], 429)]);
        $response = $this->postJson('/api/movs/questions', ['target_ref' => 'office:'.$target->id]);
        $response->assertUnprocessable()->assertJsonValidationErrors('questions');
        $this->assertStringContainsString('credits or the project spending limit', $response->json('errors.questions.0'));
        $this->assertStringNotContainsString('Private provider details', $response->getContent());
    }

    public function test_files_first_upload_asks_only_missing_details_then_rechecks_original_files(): void
    {
        [$user, $target] = $this->fixture();
        $question = ['id' => 'q1', 'label' => 'Which campus is shown in the undated record?', 'type' => 'text', 'required' => true];
        $pending = [...$this->findings(true), 'mov_title' => 'Article evidence', 'mov_description' => 'Two article records; campus needs clarification.', 'followup_questions' => [$question]];
        Http::fakeSequence()->push($this->response($pending))->push($this->response([
            ...$this->findings(false), 'mov_title' => 'Article evidence', 'mov_description' => 'Records reviewed with campus clarification.', 'followup_questions' => [],
        ]));
        $this->get(route('movs.create', ['office_target_id' => $target->id]))->assertOk()
            ->assertSee('Upload MOV and analyze files')->assertSee('name="files[]"', false)->assertDontSee('name="questionnaire_token"', false);
        Http::assertNothingSent();
        $this->post(route('movs.store'), ['mov_flow' => 'files-first', 'target_ref' => 'office:'.$target->id,
            'files' => [UploadedFile::fake()->createWithContent('articles.pdf', '%PDF-1.4 partial record'), UploadedFile::fake()->createWithContent('support.pdf', '%PDF-1.4 supporting record')]])->assertSessionHasNoErrors()->assertRedirect();
        $file = TargetMov::with('analysis')->firstOrFail();
        $this->assertFalse($file->analysis->findings['satisfies_target']);
        $this->assertNull($file->analysis->statement);
        $this->assertNull($target->assignedEmployees()->first()->pivot->completed_value);
        $this->get(route('office-targets.show', $target))->assertOk()->assertSee($question['label']);
        $report = Ipcr::create(['office_target_id' => $target->id, 'employee_id' => $user->id, 'prepared_by' => $user->id, 'title' => 'Test', 'status' => 'draft']);
        $this->assertNotEmpty(app(ReportEvidence::class)->issues($report));
        $url = route('evidence.answers', ['target-mov', $file->id]);
        $version = hash('sha256', json_encode([$question]));
        $this->postJson($url, ['analysis_version' => 'stale', 'answers' => ['q1' => 'Campus 6']])->assertUnprocessable();
        $this->postJson($url, ['analysis_version' => $version, 'answers' => []])->assertUnprocessable();
        $stranger = User::factory()->create()->assignRole('Employee');
        $this->actingAs($stranger)->postJson($url, ['analysis_version' => $version, 'answers' => ['q1' => 'Campus 6']])->assertForbidden();
        $this->actingAs($user)->post($url, ['analysis_version' => $version, 'answers' => ['q1' => 'Campus 6']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Campus 6', $file->fresh()->submission_context['answers']['q1']);
        foreach (TargetMov::all() as $member) $this->assertSame('Campus 6', $member->submission_context['answers']['q1']);
        $this->assertFalse($file->fresh()->analysis->findings['satisfies_target']);
        $this->assertNotEmpty(app(ReportEvidence::class)->issues($report));
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => str_contains(json_encode($r['input']), 'Campus 6') && str_contains(json_encode($r['input']), 'input_file'));
    }

    public function test_complete_files_need_no_questions_or_user_typed_metadata(): void
    {
        [, $target] = $this->fixture();
        Http::fakeSequence()->push($this->response([...$this->findings(true), 'mov_title' => 'Published article records', 'mov_description' => 'Two dated university articles.', 'followup_questions' => []]));
        $this->post(route('movs.store'), ['mov_flow' => 'files-first', 'target_ref' => 'office:'.$target->id,
            'files' => [UploadedFile::fake()->createWithContent('articles.pdf', '%PDF-1.4 complete records')]])->assertSessionHasNoErrors()->assertRedirect();
        $file = TargetMov::with('analysis')->firstOrFail();
        $this->assertTrue($file->analysis->findings['satisfies_target']);
        $this->assertNotNull($file->analysis->statement);
        $this->get(route('office-targets.show', $target))->assertOk()->assertSee('Published article records')->assertDontSee('Save answers and recheck MOV');
        Http::assertSentCount(1);
    }

    public function test_report_target_accepts_file_first_batch_and_denies_another_uploaders_target(): void
    {
        [$user] = $this->fixture();
        Storage::fake('public');
        $report = Ipcr::factoryLike()->create(['employee' => $user, 'prepared_by_user' => $user]);
        $target = \App\Models\PerformanceTarget::factoryLike()->create(['targetable' => $report]);
        Http::fakeSequence()->push($this->response([...$this->findings(true), 'mov_title' => 'Report evidence', 'mov_description' => 'Supporting report records.', 'followup_questions' => []]));
        $this->post(route('movs.store'), ['mov_flow' => 'files-first', 'target_ref' => 'report:'.$target->id,
            'files' => [UploadedFile::fake()->createWithContent('records.pdf', '%PDF-1.4 report records')]])->assertSessionHasNoErrors()->assertRedirect();
        $file = \App\Models\Mov::sole();
        $this->assertTrue($file->analysis->findings['satisfies_target']);
        $this->get(route('movs.show', $file))->assertOk()->assertSee('Report evidence');
        $this->actingAs(User::factory()->create()->assignRole('Employee'))
            ->postJson(route('movs.store'), ['mov_flow' => 'files-first', 'target_ref' => 'report:'.$target->id,
                'files' => [UploadedFile::fake()->createWithContent('foreign.pdf', '%PDF-1.4 unauthorized')]])->assertForbidden();
        $this->assertDatabaseCount('movs', 1);
        Http::assertSentCount(1);
    }

    public function test_questions_answers_and_multiple_files_are_analyzed_together_and_summarized_separately(): void
    {
        [$user, $target] = $this->fixture();
        config(['spms.target_assistance.enabled' => true]);
        Http::fakeSequence()->push($this->response($this->questions()))->push($this->response($this->findings(true)));
        $start = $this->post(route('movs.questions'), ['target_ref' => 'office:'.$target->id])->assertSessionHasNoErrors()->assertRedirect();
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->get($start->headers->get('Location'))->assertOk()->assertSee('How many articles')->assertSee('Which platform')->assertSee('name="files[]"', false);
        // The questionnaire upload must work without typing a title or description.
        $data = ['target_ref' => 'office:'.$target->id, 'questionnaire_token' => $query['questionnaire_token'], 'completed_value' => 2];
        $this->post(route('movs.store'), [...$data, 'answers' => ['q1' => 2], 'files' => [UploadedFile::fake()->createWithContent('article1.pdf', '%PDF-1.4 first record')]])->assertSessionHasErrors('answers.q2');
        $this->assertDatabaseCount('target_movs', 0);
        $this->post(route('movs.store'), [...$data, 'answers' => ['q1' => 2, 'q2' => 'University platform'], 'files' => [UploadedFile::fake()->createWithContent('article1.pdf', '%PDF-1.4 first record'), UploadedFile::fake()->createWithContent('article2.pdf', '%PDF-1.4 second record')]])->assertSessionHasNoErrors()->assertRedirect();
        $files = TargetMov::with('analysis')->get();
        $this->assertCount(2, $files);
        foreach ($files as $file) {
            $this->assertStringContainsString('Evidence for: University articles', $file->accomplishment);
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
