<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\PerformanceTarget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisors_can_rate_employees_with_remarks(): void
    {
        $this->seed(RoleSeeder::class);

        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create([
            'weight' => 20,
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->post(route('evaluations.store'), [
                'employee_id' => $employee->id,
                'performance_target_id' => $target->id,
                'quality_rating' => 5,
                'efficiency_rating' => 4,
                'timeliness_rating' => 4.5,
                'remarks' => 'Strong output with complete documentation.',
            ]);

        $evaluation = Evaluation::firstOrFail();

        $response->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('evaluations', [
            'employee_id' => $employee->id,
            'performance_target_id' => $target->id,
            'quality_rating' => 5,
            'efficiency_rating' => 4,
            'timeliness_rating' => 4.5,
            'rating' => 4.5,
            'computed_rating' => 0.9,
            'adjectival_rating' => 'Outstanding',
            'remarks' => 'Strong output with complete documentation.',
            'rated_by' => $supervisor->id,
        ]);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $this->seed(RoleSeeder::class);

        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create();

        $response = $this
            ->actingAs($supervisor)
            ->post(route('evaluations.store'), [
                'employee_id' => $employee->id,
                'performance_target_id' => $target->id,
                'quality_rating' => 5.5,
                'efficiency_rating' => 4,
                'timeliness_rating' => 4,
                'remarks' => 'Invalid high score.',
            ]);

        $response->assertSessionHasErrors('quality_rating');
    }

    public function test_employee_target_evaluation_must_be_unique(): void
    {
        $this->seed(RoleSeeder::class);

        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create();

        Evaluation::create([
            'employee_id' => $employee->id,
            'performance_target_id' => $target->id,
            'quality_rating' => 4,
            'efficiency_rating' => 4,
            'timeliness_rating' => 4,
            'rating' => 4,
            'computed_rating' => Evaluation::computeRating(4, (float) $target->weight),
            'adjectival_rating' => 'Very Satisfactory',
            'remarks' => 'Existing rating.',
            'rated_by' => $supervisor->id,
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->post(route('evaluations.store'), [
                'employee_id' => $employee->id,
                'performance_target_id' => $target->id,
                'quality_rating' => 4,
                'efficiency_rating' => 4.25,
                'timeliness_rating' => 4.5,
                'remarks' => 'Duplicate rating.',
            ]);

        $response->assertSessionHasErrors('performance_target_id');
    }

    public function test_supervisors_can_view_evaluations(): void
    {
        $this->seed(RoleSeeder::class);

        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $employee = User::factory()->create(['name' => 'Rated Employee']);
        $employee->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create();

        Evaluation::create([
            'employee_id' => $employee->id,
            'performance_target_id' => $target->id,
            'quality_rating' => 4,
            'efficiency_rating' => 4,
            'timeliness_rating' => 4,
            'rating' => 4,
            'computed_rating' => Evaluation::computeRating(4, (float) $target->weight),
            'adjectival_rating' => 'Very Satisfactory',
            'remarks' => 'Visible rating.',
            'rated_by' => $supervisor->id,
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->get(route('evaluations.index'));

        $response->assertOk();
        $response->assertSee('Rated Employee');
        $response->assertSee('Visible rating');
    }

    public function test_employees_cannot_manage_evaluations(): void
    {
        $this->seed(RoleSeeder::class);

        $employee = User::factory()->create();
        $employee->assignRole('Employee');

        $response = $this
            ->actingAs($employee)
            ->get(route('evaluations.index'));

        $response->assertForbidden();
    }
}
