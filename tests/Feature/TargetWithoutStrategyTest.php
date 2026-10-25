<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetWithoutStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_and_update_a_target_without_strategic_planning(): void
    {
        $this->seed(RoleSeeder::class);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $office = Office::factory()->create();
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
        $this->actingAs($director)->get(route('office-targets.create'))->assertRedirect(route('target-documents.index'));
        $this->get(route('target-documents.index'))->assertOk()
            ->assertDontSee('Strategic Alignment')->assertDontSee('Create a goal and objective first');
        $data = [
            'evaluation_cycle_id' => $cycle->id, 'title' => 'Quarterly reports',
            'success_indicator' => 'Four submitted reports', 'target_value' => 4,
            'unit_of_measure' => 'reports', 'weight' => 20, 'deadline' => '2026-12-31',
            'priority' => 'medium', 'office_ids' => [$office->id], 'office_allocations' => [$office->id => 4],
        ];
        $this->post(route('office-targets.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $target = OfficeTarget::sole();
        $this->assertNull($target->strategic_objective_id);
        $this->put(route('office-targets.update', $target), [...$data, 'title' => 'Updated reports'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Updated reports', $target->fresh()->title);
        $this->get(route('office-targets.edit', $target))->assertOk()->assertDontSee('Strategic Alignment');
    }
}
