<?php

namespace Tests\Feature;

use App\Models\Ipcr;
use App\Models\PerformanceTarget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceTargetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisors_can_add_targets_to_performance_forms(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $ipcr = Ipcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('performance-targets.store'), [
                'targetable_type' => 'ipcr',
                'targetable_id' => $ipcr->id,
                'target' => 'Submit monthly monitoring report',
                'success_indicator' => 'Report submitted on or before the deadline',
                'weight' => 25,
                'deadline' => '2026-12-31',
            ]);

        $response->assertRedirect(route('performance-targets.index'));

        $this->assertDatabaseHas('performance_targets', [
            'targetable_type' => Ipcr::class,
            'targetable_id' => $ipcr->id,
            'target' => 'Submit monthly monitoring report',
            'success_indicator' => 'Report submitted on or before the deadline',
            'created_by' => $user->id,
        ]);
    }

    public function test_weight_must_be_between_zero_and_one_hundred(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $ipcr = Ipcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('performance-targets.store'), [
                'targetable_type' => 'ipcr',
                'targetable_id' => $ipcr->id,
                'target' => 'Invalid target',
                'success_indicator' => 'Invalid indicator',
                'weight' => 101,
                'deadline' => '2026-12-31',
            ]);

        $response->assertSessionHasErrors('weight');
    }

    public function test_target_must_link_to_an_existing_supported_form(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        $response = $this
            ->actingAs($user)
            ->post(route('performance-targets.store'), [
                'targetable_type' => 'ipcr',
                'targetable_id' => 999999,
                'target' => 'Missing form target',
                'success_indicator' => 'Missing form indicator',
                'weight' => 10,
                'deadline' => '2026-12-31',
            ]);

        $response->assertNotFound();
    }

    public function test_users_can_view_targets(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $target = PerformanceTarget::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('performance-targets.index'));

        $response->assertOk();
        $response->assertSee($target->target);
    }

    public function test_employees_can_view_but_cannot_add_targets(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create();
        $target = PerformanceTarget::factoryLike()->create([
            'targetable' => $ipcr,
        ]);

        $this
            ->actingAs($user)
            ->get(route('performance-targets.index'))
            ->assertOk()
            ->assertSee($target->target)
            ->assertDontSee('Add Target');

        $this
            ->actingAs($user)
            ->get(route('performance-targets.create'))
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->post(route('performance-targets.store'), [
                'targetable_type' => 'ipcr',
                'targetable_id' => $ipcr->id,
                'target' => 'Employee cannot add target',
                'success_indicator' => 'Should be blocked',
                'weight' => 25,
                'deadline' => '2026-12-31',
            ])
            ->assertForbidden();
    }
}
