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

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('performance-targets.store'));
    }
}
