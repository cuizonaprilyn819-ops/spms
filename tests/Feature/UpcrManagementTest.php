<?php

namespace Tests\Feature;

use App\Models\Dpcr;
use App\Models\Upcr;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcrManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_can_create_upcrs_linked_to_dpcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $employee = User::factory()->create();
        $dpcr = Dpcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('upcrs.store'), [
                'dpcr_id' => $dpcr->id,
                'employee_id' => $employee->id,
                'title' => '2025 UPCR',
                'success_indicators' => 'Unit outputs completed.',
                'targets' => 'Complete assigned unit targets.',
            ]);

        $upcr = Upcr::firstOrFail();

        $response->assertRedirect(route('upcrs.show', $upcr));

        $this->assertDatabaseHas('upcrs', [
            'dpcr_id' => $dpcr->id,
            'employee_id' => $employee->id,
            'prepared_by' => $user->id,
        ]);
    }

    public function test_users_can_view_upcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');
        $upcr = Upcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('upcrs.show', $upcr));

        $response->assertOk();
        $response->assertSee($upcr->title);
    }

    public function test_upcr_is_unique_per_employee_and_dpcr(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $employee = User::factory()->create();
        $dpcr = Dpcr::factoryLike()->create();

        Upcr::create([
            'dpcr_id' => $dpcr->id,
            'employee_id' => $employee->id,
            'title' => 'Existing UPCR',
            'prepared_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('upcrs.store'), [
                'dpcr_id' => $dpcr->id,
                'employee_id' => $employee->id,
                'title' => 'Duplicate UPCR',
            ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_guests_cannot_manage_upcrs(): void
    {
        $response = $this->get(route('upcrs.index'));

        $response->assertRedirect(route('login'));
    }
}
