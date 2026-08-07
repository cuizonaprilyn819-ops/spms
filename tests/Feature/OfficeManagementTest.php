<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_create_offices(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $response = $this
            ->actingAs($user)
            ->post(route('offices.store'), [
                'name' => 'Planning and Development Office',
                'code' => 'PDO',
                'description' => 'Handles strategic planning.',
            ]);

        $response->assertRedirect(route('offices.index'));

        $this->assertDatabaseHas('offices', [
            'name' => 'Planning and Development Office',
            'code' => 'PDO',
        ]);
    }

    public function test_authenticated_users_can_update_offices(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $office = Office::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('offices.update', $office), [
                'name' => 'Updated Office',
                'code' => 'UO',
                'description' => 'Updated description.',
            ]);

        $response->assertRedirect(route('offices.index'));

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'name' => 'Updated Office',
            'code' => 'UO',
        ]);
    }

    public function test_authenticated_users_can_delete_offices(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $office = Office::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('offices.destroy', $office));

        $response->assertRedirect(route('offices.index'));

        $this->assertDatabaseMissing('offices', [
            'id' => $office->id,
        ]);
    }

    public function test_non_admin_and_non_hr_users_cannot_manage_offices(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');

        $response = $this
            ->actingAs($user)
            ->get(route('offices.index'));

        $response->assertForbidden();
    }
}
