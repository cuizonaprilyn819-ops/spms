<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChangeRequested;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_request_notifies_administrators(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');

        $this->actingAs($employee)->post(route('password.change-request'))
            ->assertRedirect()->assertSessionHas('status', 'password-change-requested');

        $notification = $administrator->notifications()->firstOrFail();
        $this->assertSame(PasswordChangeRequested::class, $notification->type);
        $this->assertSame(route('admin.password-resets.edit', $employee, false), $notification->data['url']);
    }

    public function test_only_administrator_can_reset_another_users_password(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');

        $this->actingAs($employee)->get(route('admin.password-resets.edit', $employee))->assertForbidden();
        $this->put(route('admin.password-resets.update', $employee), [
            'admin_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertForbidden();

        $this->actingAs($administrator)->get(route('admin.password-resets.edit', $employee))->assertOk();
        $this->put(route('admin.password-resets.update', $employee), [
            'admin_password' => 'wrong-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('admin_password');
        $this->assertTrue(Hash::check('password', $employee->fresh()->password));

        $this->put(route('admin.password-resets.update', $employee), [
            'admin_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('employees.index'));
        $this->assertTrue(Hash::check('NewPassword123!', $employee->fresh()->password));
        $this->assertNotNull($employee->remember_token);
    }

    public function test_hr_cannot_change_password_through_employee_edit(): void
    {
        $this->seed(RoleSeeder::class);
        $hr = User::factory()->create();
        $hr->assignRole('HR');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');

        $this->actingAs($hr)->patch(route('employees.update', $employee), [
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => 'Employee',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $employee->fresh()->password));

        $this->patch(route('employees.update', $employee), [
            'name' => 'Updated Employee',
            'email' => $employee->email,
            'role' => 'Employee',
        ])->assertRedirect(route('employees.index'));
        $this->assertSame('Updated Employee', $employee->fresh()->name);
        $this->assertTrue(Hash::check('password', $employee->password));
    }
}
