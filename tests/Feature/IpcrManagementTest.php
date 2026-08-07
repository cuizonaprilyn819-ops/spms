<?php

namespace Tests\Feature;

use App\Models\Ipcr;
use App\Models\Upcr;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_can_create_ipcrs_and_assign_employees(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $assignedEmployee = User::factory()->create();
        $upcr = Upcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('ipcrs.store'), [
                'upcr_id' => $upcr->id,
                'employee_id' => $assignedEmployee->id,
                'title' => '2025 IPCR',
                'success_indicators' => 'Individual outputs completed.',
                'targets' => 'Complete assigned individual targets.',
            ]);

        $ipcr = Ipcr::firstOrFail();

        $response->assertRedirect(route('ipcrs.show', $ipcr));

        $this->assertDatabaseHas('ipcrs', [
            'upcr_id' => $upcr->id,
            'employee_id' => $assignedEmployee->id,
            'status' => Ipcr::STATUS_DRAFT,
            'prepared_by' => $user->id,
        ]);
    }

    public function test_users_can_view_ipcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('ipcrs.show', $ipcr));

        $response->assertOk();
        $response->assertSee($ipcr->title);
    }

    public function test_supervisors_can_approve_ipcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $approver = User::factory()->create();
        $approver->assignRole('Supervisor');
        $ipcr = Ipcr::factoryLike()->create();

        $response = $this
            ->actingAs($approver)
            ->patch(route('ipcrs.approve', $ipcr));

        $response->assertRedirect(route('ipcrs.show', $ipcr));

        $ipcr->refresh();

        $this->assertSame(Ipcr::STATUS_APPROVED, $ipcr->status);
        $this->assertSame($approver->id, $ipcr->approved_by);
        $this->assertNotNull($ipcr->approved_at);
    }

    public function test_employees_cannot_approve_ipcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('ipcrs.approve', $ipcr));

        $response->assertForbidden();
        $this->assertSame(Ipcr::STATUS_DRAFT, $ipcr->fresh()->status);
    }

    public function test_ipcr_is_unique_per_employee_and_upcr(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $employee = User::factory()->create();
        $upcr = Upcr::factoryLike()->create();

        Ipcr::create([
            'upcr_id' => $upcr->id,
            'employee_id' => $employee->id,
            'title' => 'Existing IPCR',
            'status' => Ipcr::STATUS_DRAFT,
            'prepared_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('ipcrs.store'), [
                'upcr_id' => $upcr->id,
                'employee_id' => $employee->id,
                'title' => 'Duplicate IPCR',
            ]);

        $response->assertSessionHasErrors('employee_id');
    }
}
