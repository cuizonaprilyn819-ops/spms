<?php

namespace Tests\Feature;

use App\Models\{Dpcr, Ipcr, Office, Opcr, Upcr, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllRolesIpcrPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_personnel_role_can_prepare_only_their_own_ipcr(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();
        $opcr = Opcr::factoryLike()->create(['office' => $office, 'status' => Opcr::STATUS_APPROVED]);
        $dpcr = Dpcr::factoryLike()->create(['opcr' => $opcr, 'status' => Dpcr::STATUS_APPROVED]);
        $upcr = Upcr::factoryLike()->create(['dpcr' => $dpcr, 'status' => Upcr::STATUS_APPROVED]);

        foreach (['Administrator', 'HR', 'HR Head', 'HRM Office II', 'Campus Director', 'Planning Officer', 'Supervisor', 'Employee'] as $role) {
            $user = User::factory()->create(['office_id' => $office->id]);
            $user->assignRole($role);

            $this->actingAs($user)->get(route('ipcrs.create'))->assertOk()->assertSee('Prepare My IPCR');
            $this->actingAs($user)->post(route('ipcrs.store'), [
                'upcr_id' => $upcr->id,
                'employee_id' => User::factory()->create(['office_id' => $office->id])->id,
                'title' => $role.' IPCR',
            ])->assertRedirect();

            $ipcr = Ipcr::where('prepared_by', $user->id)->firstOrFail();
            $this->assertSame($user->id, $ipcr->employee_id);
        }
    }

    public function test_user_without_an_office_cannot_prepare_an_ipcr(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['office_id' => null]);
        $user->assignRole('Employee');
        $this->actingAs($user)->get(route('ipcrs.create'))->assertForbidden();
    }
}
