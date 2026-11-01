<?php

namespace Tests\Feature;

use App\Models\{EvaluationCycle, Office, OfficeTarget, TargetDocument, User, WorkflowAudit};
use App\Services\TargetDocumentAi;
use App\Support\OfficeTargetProgress;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningOfficerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_officer_opens_period_and_replaces_its_chairperson_with_history(): void
    {
        $this->seed(RoleSeeder::class);
        $officer = User::factory()->create();
        $officer->assignRole('Planning Officer');
        $office = Office::factory()->create(['campus' => config('hinunangan.campus_id'), 'is_active' => true]);
        $people = User::factory()->count(2)->create(['office_id' => $office->id]);
        $this->actingAs($officer)->post(route('evaluation-cycles.store'), ['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER])->assertSessionHasNoErrors();
        $cycle = EvaluationCycle::sole();
        foreach ($people as $person) {
            $this->patch(route('evaluation-cycles.chairperson', $cycle), ['pmt_chairperson_id' => $person->id])->assertSessionHasNoErrors();
        }
        $this->assertEquals($people->last()->id, $cycle->fresh()->pmt_chairperson_id);
        $this->assertEquals(2, WorkflowAudit::where('action', 'PMT chairperson assigned')->count());
        $this->get(route('evaluation-cycles.index'))->assertOk()->assertSee($people->last()->name);
        $this->actingAs($people->first())->patch(route('evaluation-cycles.chairperson', $cycle), ['pmt_chairperson_id' => $people->first()->id])->assertForbidden();
    }

    public function test_officer_assigns_multiple_individuals_and_rejects_people_from_other_offices(): void
    {
        $this->seed(RoleSeeder::class);
        $officer = User::factory()->create();
        $officer->assignRole('Planning Officer');
        $office = Office::factory()->create(['campus' => config('hinunangan.campus_id'), 'is_active' => true]);
        $people = User::factory()->count(2)->create(['office_id' => $office->id]);
        $outsider = User::factory()->create();
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open']);
        $document = TargetDocument::create(['created_by' => $officer->id, 'evaluation_cycle_id' => $cycle->id, 'original_name' => 'Main.pdf', 'path' => 'main.pdf', 'mime_type' => 'application/pdf', 'status' => 'review']);
        $draft = $document->drafts()->create(['proposal' => ['program' => 'Faculty development', 'program_outcome' => 'Skilled faculty', 'title' => 'Training', 'success_indicator' => 'Two trained faculty', 'source_excerpt' => 'Two trained faculty', 'cd_only' => false, 'classification_reason' => 'Source', 'suggested_offices' => [], 'target_value' => 2, 'weight' => 10, 'deadline' => '2026-12-31', 'unit_of_measure' => 'faculty']]);
        $this->mock(TargetDocumentAi::class, fn ($mock) => $mock->shouldReceive('briefings')->once()->andReturn([$office->id => ['wording' => 'Train two faculty', 'summary' => 'Training', 'recommendation' => 'delegate', 'reason' => 'Confirmed allocation']]));
        $data = ['title' => 'Training', 'success_indicator' => 'Two trained faculty', 'target_value' => 2, 'unit_of_measure' => 'faculty', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'office_ids' => [$office->id], 'allocations' => [$office->id => 2], 'assignment_type' => 'individual', 'employee_allocations' => [$people[0]->id => 1, $people[1]->id => 1], 'confirmed' => 1];
        $this->actingAs($officer)->get(route('target-documents.show', $document))->assertOk()->assertSee('Faculty development')->assertSee('Skilled faculty');
        $this->get(route('target-drafts.review', $draft))->assertOk()->assertSee($people[0]->name);
        $this->post(route('target-drafts.assign', $draft), [...$data, 'employee_allocations' => [$outsider->id => 2]])->assertSessionHasErrors('employee_allocations');
        $this->post(route('target-drafts.assign', $draft), $data)->assertSessionHasNoErrors();
        $target = OfficeTarget::sole();
        $this->assertCount(2, $target->assignedEmployees);
        $this->get(route('office-targets.show', $target))->assertOk();
    }

    public function test_shared_completion_keeps_excess_credit_with_the_actual_contributor(): void
    {
        $this->seed(RoleSeeder::class);
        $officer = User::factory()->create();
        $officer->assignRole('Planning Officer');
        $offices = Office::factory()->count(2)->create();
        $target = OfficeTarget::create(['title' => 'Shared target', 'target_value' => 2, 'unit_of_measure' => 'reports', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'success_indicator' => 'Two reports', 'created_by' => $officer->id]);
        foreach ($offices as $index => $office) {
            $person = User::factory()->create(['office_id' => $office->id]);
            $person->assignRole('Employee');
            $target->offices()->attach($office->id, ['allocated_target_value' => 1]);
            $target->assignedEmployees()->attach($person->id, ['assigned_by' => $officer->id, 'allocated_target_value' => 1, 'completed_value' => $index === 0 ? 3 : 0]);
            $result = app(OfficeTargetProgress::class)->forUser($person)->sole();
            $this->assertEquals($index === 0 ? 3 : 0, $result['completed']);
        }
        $shared = app(OfficeTargetProgress::class)->forUser($officer)->sole();
        $this->assertEquals(3, $shared['completed']);
        $this->assertEquals(100, $shared['percent']);
    }

    public function test_parent_office_supervisor_reviews_child_head_report(): void
    {
        $this->seed(RoleSeeder::class);
        $parent = Office::factory()->create();
        $child = Office::factory()->create(['parent_office_id' => $parent->id]);
        $head = User::factory()->create(['office_id' => $child->id]);
        $head->assignRole('Supervisor');
        $child->update(['head_id' => $head->id]);
        $supervisor = User::factory()->create(['office_id' => $parent->id]);
        $supervisor->assignRole('Supervisor');
        $report = \App\Models\Ipcr::factoryLike()->create(['employee' => $head, 'prepared_by_user' => $head, 'status' => 'submitted']);
        $this->actingAs($head)->patch(route('report-workflow.approve', ['ipcr', $report]))->assertForbidden();
        $this->actingAs($supervisor)->patch(route('report-workflow.approve', ['ipcr', $report]))->assertSessionHasNoErrors();
        $this->assertSame('approved', $report->fresh()->status);
    }

    public function test_office_parent_cannot_create_a_reporting_cycle(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $parent = Office::factory()->create(['campus' => config('hinunangan.campus_id')]);
        $child = Office::factory()->create(['parent_office_id' => $parent->id, 'campus' => $parent->campus, 'is_active' => true]);
        $this->actingAs($admin)->put(route('offices.update', $parent), ['name' => $parent->name, 'code' => $parent->code, 'parent_office_id' => $child->id])->assertSessionHasErrors('parent_office_id');
        $this->assertNull($parent->fresh()->parent_office_id);
    }

    public function test_no_contribution_receives_zero_even_when_shared_target_is_complete(): void
    {
        $this->seed(RoleSeeder::class);
        $officer = User::factory()->create();
        $officer->assignRole('Planning Officer');
        $person = User::factory()->create();
        $person->assignRole('Employee');
        $target = OfficeTarget::create(['title' => 'Reports', 'target_value' => 2, 'unit_of_measure' => 'reports', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'success_indicator' => 'Two reports', 'created_by' => $officer->id]);
        $target->assignedEmployees()->attach($person->id, ['assigned_by' => $officer->id, 'allocated_target_value' => 1, 'completed_value' => 0]);
        $report = \App\Models\Ipcr::factoryLike()->create(['employee' => $person, 'prepared_by_user' => $person, 'status' => 'submitted']);
        $report->update(['office_target_id' => $target->id]);
        $this->actingAs($officer)->get(route('reports.index'))->assertOk()->assertSee('Reports awaiting Planning Officer review');
        $this->patch(route('report-workflow.approve', ['ipcr', $report]), ['quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 5])->assertSessionHasNoErrors();
        $this->assertEquals(0, $report->fresh()->average_rating);
    }
}
