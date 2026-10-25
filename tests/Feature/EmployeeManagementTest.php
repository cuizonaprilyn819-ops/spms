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

    public function test_directory_search_and_office_filters_work_together(): void
    {
        $this->seed(RoleSeeder::class);
        $hr = User::factory()->create();
        $hr->assignRole('HR');
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        User::factory()->create(['name' => 'Directory Match', 'office_id' => $office->id]);
        User::factory()->create(['name' => 'Directory Other Office', 'office_id' => $otherOffice->id]);
        User::factory()->create(['name' => 'Unrelated Person', 'office_id' => $office->id]);

        $this->actingAs($hr)->get(route('employees.index', ['search' => 'directory', 'office_id' => $office->id]))
            ->assertOk()
            ->assertSee('Directory Match')
            ->assertDontSee('Directory Other Office')
            ->assertDontSee('Unrelated Person');

        $this->actingAs($hr)->get(route('employees.index', ['search' => 'no-matching-employee']))
            ->assertOk()
            ->assertSee('No employees match your filters.');
    }

    public function test_administrator_accounts_cannot_be_deleted(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $this->actingAs($hr)->get(route('employees.index'))
            ->assertOk()
            ->assertDontSee('action="'.route('employees.destroy', $administrator).'"', false)
            ->assertSee('action="'.route('employees.destroy', $hr).'"', false);

        foreach ([$hr, $administrator] as $actor) {
            $this->actingAs($actor)->delete(route('employees.destroy', $administrator))
                ->assertRedirect(route('employees.index'))
                ->assertSessionHas('status', 'Administrator accounts cannot be deleted.');

            $this->assertDatabaseHas('users', ['id' => $administrator->id]);
        }
    }

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
                'name' => 'Maria Office Head',
                'email' => 'maria.office-head@example.com',
                'office_id' => $office->id,
                'role' => 'Office Head',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect(route('employees.index'));

        $employee->refresh();

        $this->assertSame('Maria Office Head', $employee->name);
        $this->assertSame($office->id, $employee->office_id);
        $this->assertTrue($employee->hasRole('Office Head'));
    }

    public function test_administrators_can_assign_operational_roles_but_not_administrator(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');

        $this->actingAs($administrator)->get(route('employees.create'))
            ->assertOk()
            ->assertSee('Campus Director')
            ->assertSee('Office Head')
            ->assertSee('Employee')
            ->assertSee('Supervisor')
            ->assertDontSee('<option value="Administrator"', false);

        $this->actingAs($administrator)->post(route('employees.store'), [
            'name' => 'Invalid HR',
            'email' => 'invalid.hr@example.com',
            'role' => 'HR',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('role');
    }

    public function test_administrator_role_cannot_be_assigned_through_employee_management(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $this->actingAs($administrator)->post(route('employees.store'), [
            'name' => 'Second Administrator',
            'email' => 'second.admin@example.com',
            'role' => 'Administrator',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'second.admin@example.com']);
    }

    public function test_only_one_campus_director_can_be_created(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $existingDirector = User::factory()->create();
        $existingDirector->assignRole('Campus Director');

        $this->actingAs($administrator)->get(route('employees.create'))
            ->assertOk()
            ->assertDontSee('<option value="Campus Director"', false);

        $this->actingAs($administrator)->post(route('employees.store'), [
            'name' => 'Second Campus Director',
            'email' => 'second.director@example.com',
            'role' => 'Campus Director',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('role');

        $this->actingAs($administrator)->get(route('employees.edit', $existingDirector))
            ->assertOk()
            ->assertSee('<option value="Campus Director" selected>', false);
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
