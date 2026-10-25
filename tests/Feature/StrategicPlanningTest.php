<?php

namespace Tests\Feature;

use App\Models\StrategicGoal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategicPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_campus_director_can_create_a_goal_and_add_an_objective(): void
    {
        $this->seed(RoleSeeder::class);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');

        $this->actingAs($director)->get(route('strategic-goals.create'))
            ->assertOk()->assertSee('Breakthrough Goal')->assertDontSee('name="description"', false);

        $response = $this->actingAs($director)->post(route('strategic-goals.store'), [
            'title' => 'Institutional Excellence',
        ]);

        $goal = StrategicGoal::firstOrFail();
        $response->assertRedirect(route('strategic-objectives.create', $goal));

        $this->actingAs($director)->get(route('strategic-objectives.create', $goal))
            ->assertOk()->assertSee('Add Strategic Objective')->assertSee($goal->title)
            ->assertDontSee('name="description"', false);

        $objectiveResponse = $this->actingAs($director)->post(route('strategic-objectives.store', $goal), [
            'title' => 'Improve reporting timeliness',
        ]);

        $objective = $goal->objectives()->firstOrFail();
        $objectiveResponse->assertRedirect(route('strategic-objectives.create', $goal));
        $this->assertDatabaseHas('strategic_objectives', [
            'strategic_goal_id' => $goal->id,
            'title' => 'Improve reporting timeliness',
        ]);
    }

    public function test_campus_director_cannot_manage_another_directors_goal(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('Campus Director');
        $otherDirector = User::factory()->create();
        $otherDirector->assignRole('Campus Director');
        $goal = StrategicGoal::create(['title' => 'Private Goal', 'description' => 'Owner-managed goal.', 'created_by' => $owner->id]);

        $this->actingAs($otherDirector)->get(route('strategic-goals.edit', $goal))->assertForbidden();
        $this->actingAs($otherDirector)->post(route('strategic-objectives.store', $goal), ['title' => 'Unauthorized objective'])->assertForbidden();
    }

    public function test_objective_list_supports_multiple_objectives_and_targets(): void
    {
        $this->seed(RoleSeeder::class);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $goal = StrategicGoal::create(['title' => 'HR Development', 'description' => '', 'created_by' => $director->id]);
        $office = \App\Models\Office::factory()->create();
        $cycle = \App\Models\EvaluationCycle::create([
            'year' => 2026, 'semester' => '2nd Semester', 'status' => 'open',
        ]);
        $this->actingAs($director);
        foreach (['Develop employee skills', 'Improve personnel records'] as $title) {
            $this->post(route('strategic-objectives.store', $goal), compact('title'))
                ->assertRedirect(route('strategic-objectives.create', $goal));
        }
        $objectives = $goal->objectives()->get();
        $this->assertCount(2, $objectives);
        foreach (['Training session one', 'Training session two'] as $title) {
            $this->post(route('office-targets.store'), [
                'strategic_objective_id' => $objectives[0]->id, 'evaluation_cycle_id' => $cycle->id,
                'title' => $title, 'success_indicator' => 'One completed session',
                'target_value' => 1, 'unit_of_measure' => 'session', 'weight' => 20,
                'start_date' => '2026-07-01', 'deadline' => '2026-12-31', 'priority' => 'high',
                'office_ids' => [$office->id], 'office_allocations' => [$office->id => 1],
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame(2, $objectives[0]->officeTargets()->count());
        $this->assertSame(0, $objectives[1]->officeTargets()->count());
        $page = $this->get(route('strategic-objectives.create', $goal))->assertOk()
            ->assertSee('Develop employee skills')->assertSee('Improve personnel records')
            ->assertSee('Training session one')->assertSee('Training session two')
            ->assertSee('Add Objective')->assertDontSee('Save &amp; Add Target', false);
        foreach ($objectives as $objective) {
            $page->assertSee(route('office-targets.create', ['strategic_objective_id' => $objective->id]), false);
            $this->get(route('office-targets.create', ['strategic_objective_id' => $objective->id]))
                ->assertOk()->assertViewHas('preselectedObjectiveId', $objective->id);
        }
        $otherDirector = User::factory()->create();
        $otherDirector->assignRole('Campus Director');
        $this->actingAs($otherDirector)->get(route('strategic-objectives.create', $goal))->assertForbidden();
    }
}
