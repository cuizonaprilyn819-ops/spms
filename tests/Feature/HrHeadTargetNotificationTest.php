<?php

namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrHeadTargetNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_head_is_notified_and_can_manage_only_own_office_targets(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('HR Head');
        $outsider = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $outsider->assignRole('HR Head');
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $target = OfficeTarget::create([
            'title' => 'HR training delivery', 'success_indicator' => 'Two sessions',
            'target_value' => 2, 'unit_of_measure' => 'sessions', 'weight' => 20,
            'deadline' => '2026-12-31', 'priority' => 'high', 'created_by' => $director->id,
        ]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 2]);
        $target->notifyAssignedSupervisors([$office->id]);
        $target->notifyAssignedSupervisors([$office->id]);
        $this->assertSame(1, $head->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
        $this->actingAs($head)->get($head->notifications()->sole()->data['url'])
            ->assertOk()->assertSee('HR training delivery')->assertSee('Assign to Myself or Employees');
        $this->get(route('performance-targets.index'))->assertOk()->assertSee('HR training delivery');
        $this->get(route('dashboard'))->assertOk()->assertViewHas('personalDashboard', fn ($data) => $data['assignedTargets'] === 1);
        $this->post(route('office-targets.take', $target), ['allocation' => 1])->assertRedirect();
        $this->assertTrue($target->assignedEmployees()->whereKey($head->id)->exists());
        $this->actingAs($outsider)->get(route('office-targets.show', $target))->assertForbidden();
        $this->post(route('office-targets.take', $target), ['allocation' => 1])->assertForbidden();
        $this->get(route('office-targets.index'))->assertOk()->assertDontSee('HR training delivery');
    }
}
