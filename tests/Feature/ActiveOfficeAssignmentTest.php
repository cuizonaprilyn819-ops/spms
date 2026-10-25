<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveOfficeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_offices_cannot_be_assigned_but_current_assignment_can_be_retained(): void
    {
        $this->seed(RoleSeeder::class);
        $hr = User::factory()->create();
        $hr->assignRole('HR');
        $inactive = Office::factory()->create(['is_active' => false]);
        $active = Office::factory()->create(['is_active' => true, 'campus' => 6]);
        $otherCampus = Office::factory()->create(['is_active' => true, 'campus' => 2]);
        $employee = User::factory()->create(['office_id' => $inactive->id]);
        $employee->assignRole('Employee');
        $this->actingAs($hr)->get(route('employees.create'))->assertViewHas('offices', fn ($offices) => $offices->contains($active) && ! $offices->contains($inactive) && ! $offices->contains($otherCampus));
        $data = ['name' => 'New Employee', 'email' => 'new@example.com', 'office_id' => $inactive->id, 'role' => 'Employee', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'];
        $this->post(route('employees.store'), $data)->assertSessionHasErrors('office_id');
        $this->post(route('employees.store'), [...$data, 'office_id' => $otherCampus->id])->assertSessionHasErrors('office_id');
        unset($data['password'], $data['password_confirmation']);
        $data['email'] = $employee->email;
        $this->patch(route('employees.update', $employee), $data)->assertSessionHasNoErrors();
        $this->assertSame($inactive->id, $employee->fresh()->office_id);
    }

    public function test_sync_is_scoped_repeatable_and_preserves_employee_links(): void
    {
        $office = Office::factory()->create(['name' => 'Budget Office #207', 'code' => 'Budget Office', 'campus' => 6, 'is_active' => false]);
        $retired = Office::factory()->create(['campus' => 6]);
        $other = Office::factory()->create(['campus' => 2]);
        $employee = User::factory()->create(['office_id' => $retired->id]);
        $this->artisan('offices:sync-hinunangan', ['--dry-run' => true])->assertSuccessful();
        $this->assertFalse($office->fresh()->is_active);
        $this->artisan('offices:sync-hinunangan')->assertSuccessful();
        $count = Office::count();
        $this->artisan('offices:sync-hinunangan')->assertSuccessful();
        $this->assertSame($count, Office::count());
        $this->assertSame(55, Office::where('campus', 6)->where('is_active', true)->count());
        $this->assertTrue($office->fresh()->is_active);
        $this->assertTrue($other->fresh()->is_active);
        $this->assertFalse($retired->fresh()->is_active);
        $this->assertSame($retired->id, $employee->fresh()->office_id);
    }
}
