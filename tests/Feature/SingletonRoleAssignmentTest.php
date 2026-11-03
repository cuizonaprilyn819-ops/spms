<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingletonRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole('Administrator'));
    }

    public function test_occupied_singleton_roles_are_hidden_and_cannot_be_assigned_by_create_or_update(): void
    {
        $this->administrator();
        foreach (['Planning Officer', 'HR Head', 'Campus Director'] as $index => $role) {
            $holder = User::factory()->create()->assignRole($role);
            $employee = User::factory()->create()->assignRole('Employee');
            $this->get(route('employees.create'))->assertOk()->assertDontSee('<option value="'.$role.'"', false);
            $this->get(route('employees.edit', $employee))->assertOk()->assertDontSee('<option value="'.$role.'"', false);
            $data = ['name' => 'New holder', 'email' => 'holder'.$index.'@example.com', 'role' => $role, 'password' => 'Password123!', 'password_confirmation' => 'Password123!'];
            $this->post(route('employees.store'), $data)->assertSessionHasErrors('role');
            $this->assertDatabaseMissing('users', ['email' => $data['email']]);
            $this->patch(route('employees.update', $employee), ['name' => $employee->name, 'email' => $employee->email, 'role' => $role])->assertSessionHasErrors('role');
            $this->assertTrue($employee->fresh()->hasRole('Employee'));
            $this->get(route('employees.edit', $holder))->assertOk()->assertSee('<option value="'.$role.'" selected>', false);
            $this->patch(route('employees.update', $holder), ['name' => 'Updated holder', 'email' => $holder->email, 'role' => $role])->assertSessionHasNoErrors();
            $this->assertSame(1, User::role($role)->count());
        }
    }

    public function test_releasing_a_role_allows_a_new_holder(): void
    {
        $this->administrator();
        foreach (['Planning Officer', 'HR Head', 'Campus Director'] as $index => $role) {
            $holder = User::factory()->create()->assignRole($role);
            $this->patch(route('employees.update', $holder), ['name' => $holder->name, 'email' => $holder->email, 'role' => 'Employee'])->assertSessionHasNoErrors();
            $this->get(route('employees.create'))->assertOk()->assertSee('<option value="'.$role.'"', false);
            $email = 'replacement'.$index.'@example.com';
            $this->post(route('employees.store'), ['name' => 'Replacement', 'email' => $email, 'role' => $role, 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertSessionHasNoErrors();
            $this->assertSame($email, User::role($role)->sole()->email);
        }
    }

    public function test_legacy_hr_occupies_the_hr_seat_without_changing_the_existing_holders_role(): void
    {
        $this->administrator();
        $holder = User::factory()->create()->assignRole('HR');
        $this->get(route('employees.create'))->assertOk()->assertDontSee('<option value="HR Head"', false);
        $this->post(route('employees.store'), ['name' => 'Second HR', 'email' => 'secondhr@example.com', 'role' => 'HR Head', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertSessionHasErrors('role');
        $this->patch(route('employees.update', $holder), ['name' => 'Updated HR', 'email' => $holder->email, 'role' => 'HR'])->assertSessionHasNoErrors();
        $this->assertTrue($holder->fresh()->hasRole('HR'));
    }
}
