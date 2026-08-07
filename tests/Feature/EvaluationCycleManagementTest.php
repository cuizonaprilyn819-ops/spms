<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationCycleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_create_evaluation_cycles(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $response = $this
            ->actingAs($user)
            ->post(route('evaluation-cycles.store'), [
                'year' => 2025,
                'semester' => EvaluationCycle::FIRST_SEMESTER,
            ]);

        $response->assertRedirect(route('evaluation-cycles.index'));

        $this->assertDatabaseHas('evaluation_cycles', [
            'year' => 2025,
            'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_CLOSED,
        ]);
    }

    public function test_year_and_semester_combination_must_be_unique(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

        EvaluationCycle::create([
            'year' => 2025,
            'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_CLOSED,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('evaluation-cycles.store'), [
                'year' => 2025,
                'semester' => EvaluationCycle::FIRST_SEMESTER,
            ]);

        $response->assertSessionHasErrors('semester');
    }

    public function test_opening_a_cycle_closes_other_open_cycles(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('HR');

        $firstCycle = EvaluationCycle::create([
            'year' => 2025,
            'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $secondCycle = EvaluationCycle::create([
            'year' => 2025,
            'semester' => EvaluationCycle::SECOND_SEMESTER,
            'status' => EvaluationCycle::STATUS_CLOSED,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('evaluation-cycles.open', $secondCycle));

        $response->assertRedirect(route('evaluation-cycles.index'));

        $this->assertTrue($secondCycle->fresh()->isOpen());
        $this->assertFalse($firstCycle->fresh()->isOpen());
    }

    public function test_admin_users_can_close_cycles(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $cycle = EvaluationCycle::create([
            'year' => 2025,
            'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('evaluation-cycles.close', $cycle));

        $response->assertRedirect(route('evaluation-cycles.index'));

        $cycle->refresh();

        $this->assertSame(EvaluationCycle::STATUS_CLOSED, $cycle->status);
        $this->assertNotNull($cycle->closed_at);
    }

    public function test_non_admin_and_non_hr_users_cannot_manage_cycles(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');

        $response = $this
            ->actingAs($user)
            ->get(route('evaluation-cycles.index'));

        $response->assertForbidden();
    }
}
