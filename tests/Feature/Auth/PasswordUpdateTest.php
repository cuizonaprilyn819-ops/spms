<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_non_administrator_cannot_update_password_directly(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Employee');

        $this->actingAs($user)->get('/profile')->assertOk()->assertDontSee('Update Password');
        $this->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
