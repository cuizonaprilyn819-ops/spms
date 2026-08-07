<?php

namespace Tests\Feature;

use App\Models\Dpcr;
use App\Models\Opcr;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DpcrManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisors_can_create_dpcrs_linked_to_opcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $employee = User::factory()->create();
        $opcr = Opcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('dpcrs.store'), [
                'opcr_id' => $opcr->id,
                'employee_id' => $employee->id,
                'title' => '2025 DPCR',
                'success_indicators' => 'Division outputs completed.',
                'targets' => 'Complete 100% of assigned outputs.',
            ]);

        $dpcr = Dpcr::firstOrFail();

        $response->assertRedirect(route('dpcrs.show', $dpcr));

        $this->assertDatabaseHas('dpcrs', [
            'opcr_id' => $opcr->id,
            'employee_id' => $employee->id,
            'status' => Dpcr::STATUS_DRAFT,
            'prepared_by' => $user->id,
        ]);
    }

    public function test_users_can_view_dpcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Office Head');
        $dpcr = Dpcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('dpcrs.show', $dpcr));

        $response->assertOk();
        $response->assertSee($dpcr->title);
    }

    public function test_office_heads_can_approve_dpcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $approver = User::factory()->create();
        $approver->assignRole('Office Head');
        $dpcr = Dpcr::factoryLike()->create();

        $response = $this
            ->actingAs($approver)
            ->patch(route('dpcrs.approve', $dpcr));

        $response->assertRedirect(route('dpcrs.show', $dpcr));

        $dpcr->refresh();

        $this->assertSame(Dpcr::STATUS_APPROVED, $dpcr->status);
        $this->assertSame($approver->id, $dpcr->approved_by);
        $this->assertNotNull($dpcr->approved_at);
    }

    public function test_supervisors_cannot_approve_dpcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $dpcr = Dpcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('dpcrs.approve', $dpcr));

        $response->assertForbidden();
        $this->assertSame(Dpcr::STATUS_DRAFT, $dpcr->fresh()->status);
    }

    public function test_dpcr_is_unique_per_employee_and_opcr(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $employee = User::factory()->create();
        $opcr = Opcr::factoryLike()->create();

        Dpcr::create([
            'opcr_id' => $opcr->id,
            'employee_id' => $employee->id,
            'title' => 'Existing DPCR',
            'status' => Dpcr::STATUS_DRAFT,
            'prepared_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('dpcrs.store'), [
                'opcr_id' => $opcr->id,
                'employee_id' => $employee->id,
                'title' => 'Duplicate DPCR',
            ]);

        $response->assertSessionHasErrors('employee_id');
    }
}
