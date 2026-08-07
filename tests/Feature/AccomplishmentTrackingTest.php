<?php

namespace Tests\Feature;

use App\Models\Accomplishment;
use App\Models\PerformanceTarget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccomplishmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_submit_accomplishments_for_targets(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('accomplishments.store'), [
                'performance_target_id' => $target->id,
                'details' => 'Completed the required monthly monitoring report.',
                'completion_percentage' => 75,
            ]);

        $accomplishment = Accomplishment::firstOrFail();

        $response->assertRedirect(route('accomplishments.show', $accomplishment));

        $this->assertDatabaseHas('accomplishments', [
            'performance_target_id' => $target->id,
            'details' => 'Completed the required monthly monitoring report.',
            'submitted_by' => $user->id,
        ]);
    }

    public function test_users_can_edit_accomplishment_submissions(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create();
        $accomplishment = Accomplishment::factoryLike()->create([
            'performance_target' => $target,
            'submitted_by_user' => $user,
            'completion_percentage' => 25,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('accomplishments.update', $accomplishment), [
                'performance_target_id' => $target->id,
                'details' => 'Updated accomplishment progress.',
                'completion_percentage' => 90,
            ]);

        $response->assertRedirect(route('accomplishments.show', $accomplishment));

        $accomplishment->refresh();

        $this->assertSame('Updated accomplishment progress.', $accomplishment->details);
        $this->assertSame('90.00', $accomplishment->completion_percentage);
    }

    public function test_completion_percentage_must_be_between_zero_and_one_hundred(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('accomplishments.store'), [
                'performance_target_id' => $target->id,
                'details' => 'Invalid completion.',
                'completion_percentage' => 120,
            ]);

        $response->assertSessionHasErrors('completion_percentage');
    }

    public function test_users_can_view_accomplishment_tracking(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $accomplishment = Accomplishment::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('accomplishments.index'));

        $response->assertOk();
        $response->assertSee($accomplishment->details);
    }
}
