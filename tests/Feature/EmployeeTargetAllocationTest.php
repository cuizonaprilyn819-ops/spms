<?php
namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTargetAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_can_share_office_allocation_with_employees_without_exceeding_it(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $office = Office::factory()->create();
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('HR Head');
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $outsider = User::factory()->create();
        $outsider->assignRole('Employee');
        $target = OfficeTarget::create(['title' => 'Training', 'success_indicator' => 'Two sessions',
            'target_value' => 10, 'unit_of_measure' => 'sessions', 'weight' => 20,
            'deadline' => '2026-12-31', 'priority' => 'high', 'created_by' => $head->id]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 2]);
        $url = route('office-targets.assign-employees', $target);
        $this->actingAs($head)->get(route('office-targets.show', $target))->assertOk()->assertSee('Assign to myself:');
        $this->post($url, ['employee_ids' => [$head->id, $employee->id], 'allocations' => [$head->id => 1, $employee->id => 2]])->assertSessionHasErrors('allocations');
        $this->assertSame(0, $target->assignedEmployees()->count());
        $this->post($url, ['employee_ids' => [$outsider->id], 'allocations' => [$outsider->id => 1]])->assertForbidden();
        $this->post($url, ['employee_ids' => [$head->id, $employee->id], 'allocations' => [$head->id => 1, $employee->id => 1]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $target->assignedEmployees()->count());
        $mine = $target->assignedEmployees()->whereKey($head->id)->first();
        $this->assertEquals(1, $mine->pivot->allocated_target_value);
        $this->assertNotNull($mine->pivot->accepted_at);
        $this->assertNull($target->assignedEmployees()->whereKey($employee->id)->first()->pivot->accepted_at);
        $this->get(route('office-targets.show', $target))->assertOk()->assertSee('Accomplishment Details');
        $this->post($url, ['employee_ids' => [$head->id], 'allocations' => [$head->id => 2]])->assertSessionHasErrors('allocations');
        $this->assertEquals(1, $target->assignedEmployees()->whereKey($head->id)->first()->pivot->allocated_target_value);
        $this->post($url, ['employee_ids' => [$employee->id], 'allocations' => [$employee->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(1, $employee->notifications()->count());
        $this->post(route('office-targets.take', $target))->assertSessionHasErrors();
    }
}
