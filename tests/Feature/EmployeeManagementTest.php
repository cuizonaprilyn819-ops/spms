<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_create_employees_with_office_and_role(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('HR');
        $office = Office::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('employees.store'), [
                'name' => 'Juan Employee',
                'email' => 'juan.employee@example.com',
                'office_id' => $office->id,
                'role' => 'Employee',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertRedirect(route('employees.index'));

        $employee = User::where('email', 'juan.employee@example.com')->firstOrFail();

        $this->assertSame($office->id, $employee->office_id);
        $this->assertTrue($employee->hasRole('Employee'));
    }

    public function test_authenticated_users_can_update_employee_office_and_role(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $employee = User::factory()->create();
        $office = Office::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('employees.update', $employee), [
                'name' => 'Maria Supervisor',
                'email' => 'maria.supervisor@example.com',
                'office_id' => $office->id,
                'role' => 'Supervisor',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect(route('employees.index'));

        $employee->refresh();

        $this->assertSame('Maria Supervisor', $employee->name);
        $this->assertSame($office->id, $employee->office_id);
        $this->assertTrue($employee->hasRole('Supervisor'));
    }

    public function test_authenticated_users_can_delete_employee_accounts_except_their_own_account(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('HR');
        $employee = User::factory()->create();

        $deleteEmployee = $this
            ->actingAs($user)
            ->delete(route('employees.destroy', $employee));

        $deleteEmployee->assertRedirect(route('employees.index'));
        $this->assertDatabaseMissing('users', ['id' => $employee->id]);

        $deleteSelf = $this
            ->actingAs($user)
            ->delete(route('employees.destroy', $user));

        $deleteSelf->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_non_admin_and_non_hr_users_cannot_manage_employees(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');

        $response = $this
            ->actingAs($user)
            ->get(route('employees.index'));

        $response->assertForbidden();
    }
}
