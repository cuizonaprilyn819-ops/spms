<?php

namespace Tests\Feature;

use App\Models\{EvaluationCycle, Office, OfficeTarget, TargetProposal, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeHeadTargetProposalTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RoleSeeder::class);
        $parent = Office::factory()->create(['campus' => 6]);
        $office = Office::factory()->create(['campus' => 6, 'is_active' => true, 'parent_office_id' => $parent->id]);
        $head = User::factory()->create(['office_id' => $office->id])->assignRole('Supervisor');
        $planner = User::factory()->create()->assignRole('Planning Officer');
        $director = User::factory()->create(['office_id' => $parent->id])->assignRole('Campus Director');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open', 'planning_enabled' => false]);
        $data = ['evaluation_cycle_id' => $cycle->id, 'title' => 'Maintenance target', 'program' => 'Campus operations', 'program_outcome' => 'Reliable systems', 'success_indicator' => 'One maintenance conducted within the rating period', 'target_value' => 1, 'unit_of_measure' => 'maintenance', 'weight' => 10, 'priority' => 'medium', 'justification' => 'Required preventive maintenance'];
        return compact('head', 'planner', 'director', 'office', 'cycle', 'data');
    }

    public function test_head_can_add_own_office_target_but_only_planning_validation_activates_it(): void
    {
        extract($this->fixture());
        $this->actingAs($head)->get(route('office-target-proposals.create'))->assertOk()->assertSee($office->name);
        $this->get(route('performance-targets.index'))->assertOk()->assertSee('Add office target');
        $this->post(route('office-target-proposals.store'), [...$data, 'office_id' => 99999])->assertSessionHasNoErrors()->assertRedirect();
        $proposal = TargetProposal::sole();
        $this->assertSame($office->id, $proposal->office_id);
        $this->assertSame('pending', $proposal->state);
        $this->assertDatabaseCount('office_targets', 0);
        $this->assertSame(1, $planner->notifications()->count());
        $decision = ['decision' => 'approve', 'review_notes' => 'Relevant and measurable; validated.'];
        $this->post(route('office-target-proposals.review', $proposal), $decision)->assertForbidden();
        $this->actingAs($planner)->get(route('office-target-proposals.index'))->assertOk()->assertSee('Maintenance target');
        $this->post(route('office-target-proposals.review', $proposal), $decision)->assertSessionHasNoErrors();
        $target = OfficeTarget::sole();
        $this->assertSame([$office->id], $target->offices->modelKeys());
        $this->assertEquals(1, $target->offices->sole()->pivot->allocated_target_value);
        $this->assertSame('2026-12-31', $target->deadline->toDateString());
        $this->assertSame('accepted', $proposal->fresh()->state);
        $this->assertSame(1, $director->notifications()->count());
        $this->post(route('office-target-proposals.review', $proposal), $decision)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('office_targets', 1);
        $this->get(route('planning.index'))->assertOk()->assertViewHas('proposals', fn ($items) => $items->isEmpty());
    }

    public function test_explicit_head_can_revise_returned_target_and_ordinary_employee_cannot_add(): void
    {
        extract($this->fixture());
        $employee = User::factory()->create(['office_id' => $office->id])->assignRole('Employee');
        $this->actingAs($employee)->get(route('office-target-proposals.create'))->assertForbidden();
        $this->post(route('office-target-proposals.store'), $data)->assertForbidden();
        $office->update(['head_id' => $employee->id]);
        $employee->unsetRelation('office');
        $this->post(route('office-target-proposals.store'), $data)->assertSessionHasNoErrors();
        $proposal = TargetProposal::sole();
        $this->actingAs($planner)->post(route('office-target-proposals.review', $proposal), ['decision' => 'return', 'review_notes' => 'Clarify the scope.'])->assertSessionHasNoErrors();
        $this->actingAs($employee)->get(route('office-target-proposals.create', ['proposal_id' => $proposal->id]))->assertOk()->assertSee('Clarify the scope.');
        $this->post(route('office-target-proposals.store'), [...$data, 'proposal_id' => $proposal->id, 'justification' => 'Clarified scope.'])->assertSessionHasNoErrors();
        $this->assertSame('pending', $proposal->fresh()->state);
        $this->assertDatabaseCount('target_proposals', 1);
        $this->assertDatabaseCount('office_targets', 0);
        $cycle->update(['status' => 'closed']);
        $this->actingAs($planner)->post(route('office-target-proposals.review', $proposal), ['decision' => 'approve', 'review_notes' => 'Reviewed'])->assertStatus(422);
        $this->assertDatabaseCount('office_targets', 0);
    }
}
