<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelOfficeEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_and_rendered_api_include_the_current_inactive_office_and_active_campus_choices(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $retained = Office::factory()->create(['name' => 'Retained inactive office', 'campus' => 3, 'is_active' => false]);
        $active = Office::factory()->create(['name' => 'Active campus choice', 'campus' => 6, 'is_active' => true]);
        $unrelated = Office::factory()->create(['name' => 'Unrelated inactive office', 'campus' => 6, 'is_active' => false]);
        $foreign = Office::factory()->create(['name' => 'Other campus choice', 'campus' => 3, 'is_active' => true]);
        $employee = User::factory()->create(['office_id' => $retained->id]);
        $employee->assignRole('Employee');

        $this->actingAs($administrator)->get(route('employees.edit', $employee))
            ->assertOk()->assertSee($retained->name)->assertSee($active->name)
            ->assertDontSee($unrelated->name)->assertDontSee($foreign->name);
        $response = $this->actingAs($administrator)->getJson(route('api.employees.edit', $employee))->assertOk();
        $this->assertStringContainsString($retained->name, $response->json('html'));
        $this->assertStringNotContainsString($unrelated->name, $response->json('html'));
        $this->assertSame($retained->id, $employee->fresh()->office_id);
    }

    public function test_saving_personnel_can_retain_an_inactive_current_office_without_resetting_the_password(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $office = Office::factory()->create(['campus' => 3, 'is_active' => false]);
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $passwordHash = $employee->password;
        $this->actingAs($administrator)->patch(route('employees.update', $employee), [
            'name' => 'Updated personnel name', 'email' => $employee->email,
            'office_id' => $office->id, 'role' => 'Employee',
        ])->assertRedirect(route('employees.index'))->assertSessionHasNoErrors();
        $this->assertSame($office->id, $employee->fresh()->office_id);
        $this->assertSame($passwordHash, $employee->fresh()->password);
        $this->assertSame('Updated personnel name', $employee->fresh()->name);
    }

    public function test_editor_fix_does_not_allow_a_new_inactive_or_cross_campus_assignment(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $current = Office::factory()->create(['campus' => 6, 'is_active' => true]);
        $employee = User::factory()->create(['office_id' => $current->id]);
        $employee->assignRole('Employee');
        foreach ([['campus' => 6, 'is_active' => false], ['campus' => 3, 'is_active' => true]] as $attributes) {
            $office = Office::factory()->create($attributes);
            $this->actingAs($administrator)->patch(route('employees.update', $employee), [
                'name' => $employee->name, 'email' => $employee->email, 'office_id' => $office->id, 'role' => 'Employee',
            ])->assertSessionHasErrors('office_id');
            $this->assertSame($current->id, $employee->fresh()->office_id);
        }
    }
}
