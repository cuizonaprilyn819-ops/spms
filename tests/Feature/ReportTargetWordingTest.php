<?php

namespace Tests\Feature;

use App\Models\{EvaluationCycle, Ipcr, Office, OfficeTarget, TargetDocument, User};
use App\Services\{ReportTargetContext, TargetDocumentAi};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportTargetWordingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create(['campus' => 6]);
        $user = User::factory()->create(['office_id' => $office->id])->assignRole('Supervisor');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open']);
        $target = OfficeTarget::create(['title' => 'Articles', 'success_indicator' => '44 articles submitted', 'target_value' => 44, 'unit_of_measure' => 'articles', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $user->id, 'evaluation_cycle_id' => $cycle->id]);
        $target->offices()->attach($office, ['allocated_target_value' => 44]);
        $target->assignedEmployees()->attach($user, ['allocated_target_value' => 2, 'accepted_at' => now(), 'assigned_by' => $user->id]);
        $document = TargetDocument::create(['created_by' => $user->id, 'evaluation_cycle_id' => $cycle->id, 'original_name' => 'source.pdf', 'path' => 'source.pdf', 'mime_type' => 'application/pdf', 'status' => 'review']);
        $document->drafts()->create(['office_target_id' => $target->id, 'proposal' => ['program' => 'SPOTLIGHT', 'program_outcome' => 'Stronger university visibility', 'source_excerpt' => 'SPOTLIGHT: 44 articles submitted']]);
        $ipcr = Ipcr::create(['office_target_id' => $target->id, 'employee_id' => $user->id, 'prepared_by' => $user->id, 'title' => 'Articles IPCR', 'success_indicators' => '44 articles submitted', 'targets' => '44 articles submitted', 'status' => 'draft']);
        return compact('user', 'target', 'ipcr');
    }

    public function test_existing_report_paraphrases_hierarchy_and_uses_personal_quantity_with_source_retained(): void
    {
        extract($this->fixture());
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test', 'spms.ai.model' => 'test']);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['strategic_program' => 'SPOTLIGHT university communications', 'program_outcome' => 'Increase the university’s public visibility', 'success_indicators' => 'Submit 2 articles for university publication.'])]]]]])]);
        $this->actingAs($user)->get(route('report-workflow.edit', ['ipcr', $ipcr]))->assertOk()->assertSee('SPOTLIGHT')->assertSee('Program Outcome');
        $this->post(route('report-workflow.paraphrase', ['ipcr', $ipcr]))->assertSessionHasNoErrors();
        $ipcr->refresh();
        $this->assertSame('Submit 2 articles for university publication.', $ipcr->success_indicators);
        $context = app(ReportTargetContext::class)->display($ipcr);
        $this->assertSame('SPOTLIGHT', $context['source']['strategic_program']);
        $this->assertSame('44 articles submitted', $context['source']['success_indicators']);
        $this->assertEquals(2, $context['source']['quantity']);
        $this->get(route('reports.show', 'ipcr'))->assertOk()->assertSee('SPOTLIGHT university communications')->assertSee('Increase the university');
        $this->get(route('reports.excel', 'ipcr'))->assertOk();
        $ipcr->update(['status' => 'approved']);
        $this->post(route('report-workflow.paraphrase', ['ipcr', $ipcr]))->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_wrong_quantity_and_unreadable_hierarchy_do_not_invent_source_fields(): void
    {
        $this->fixture();
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test', 'spms.ai.model' => 'test']);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['strategic_program' => 'Invented', 'program_outcome' => 'Invented', 'success_indicators' => 'Submit 44 articles.'])]]]]])]);
        $this->expectException(\RuntimeException::class);
        app(TargetDocumentAi::class)->reportWording(['strategic_program' => null, 'program_outcome' => null, 'quantity' => 2, 'unit_of_measure' => 'articles']);
    }

    public function test_new_ipcr_from_an_assigned_target_is_already_paraphrased(): void
    {
        extract($this->fixture());
        $ipcr->delete();
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test']);
        $this->mock(TargetDocumentAi::class, fn ($mock) => $mock->shouldReceive('reportWording')->once()
            ->withArgs(fn ($source) => $source['strategic_program'] === 'SPOTLIGHT' && (float) $source['quantity'] === 2.0)
            ->andReturn(['strategic_program' => 'SPOTLIGHT communications', 'program_outcome' => 'Improve public visibility', 'success_indicators' => 'Submit 2 articles for publication.']));
        $this->actingAs($user)->post(route('ipcrs.store'), ['office_target_id' => $target->id, 'title' => 'My articles', 'success_indicators' => '44 articles submitted'])->assertSessionHasNoErrors()->assertRedirect();
        $created = Ipcr::sole();
        $this->assertSame('Submit 2 articles for publication.', $created->success_indicators);
        $this->assertSame('SPOTLIGHT communications', app(ReportTargetContext::class)->display($created)['strategic_program']);
    }
}
