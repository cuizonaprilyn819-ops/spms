<?php

namespace Tests\Feature;

use App\Models\Dpcr;
use App\Models\Ipcr;
use App\Models\Office;
use App\Models\Opcr;
use App\Models\Upcr;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HrmOfficeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrm_office_ii_can_view_employees_offices_forms_and_reports(): void
    {
        $this->seed(RoleSeeder::class);

        $hrm = User::factory()->create();
        $hrm->assignRole('HRM Office II');
        Office::factory()->create(['name' => 'HRM Office']);
        User::factory()->create(['name' => 'Visible Employee']);
        $ipcr = Ipcr::factoryLike()->create();
        $upcr = $ipcr->upcr;
        $dpcr = $upcr->dpcr;
        $opcr = $dpcr->opcr;

        $this->actingAs($hrm)
            ->get(route('offices.index'))
            ->assertOk()
            ->assertSee('HRM Office')
            ->assertDontSee('Add Office')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');
        $this->actingAs($hrm)->get(route('employees.index'))->assertOk()->assertSee('Visible Employee');
        $this->actingAs($hrm)->get(route('opcrs.show', $opcr))->assertOk();
        $this->actingAs($hrm)->get(route('dpcrs.show', $dpcr))->assertOk();
        $this->actingAs($hrm)->get(route('upcrs.show', $upcr))->assertOk();
        $this->actingAs($hrm)->get(route('ipcrs.show', $ipcr))->assertOk();
        $this->actingAs($hrm)->get(route('reports.show', 'ipcr'))->assertOk();
    }

    public function test_hrm_office_ii_can_add_employee_accounts(): void
    {
        $this->seed(RoleSeeder::class);

        $hrm = User::factory()->create();
        $hrm->assignRole('HRM Office II');
        $office = Office::factory()->create();

        $this
            ->actingAs($hrm)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSee('Employee')
            ->assertDontSee('<option value="Administrator"', false)
            ->assertSee('Office Head');

        $this
            ->actingAs($hrm)
            ->post(route('employees.store'), [
                'name' => 'New HRM Added Employee',
                'email' => 'new.hrm.employee@example.com',
                'office_id' => $office->id,
                'role' => 'Employee',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect(route('employees.index'));

        $employee = User::where('email', 'new.hrm.employee@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Password123!', $employee->password));
        $this->assertTrue($employee->hasRole('Employee'));
    }

    public function test_hrm_office_ii_cannot_manage_offices_or_modify_employee_accounts(): void
    {
        $this->seed(RoleSeeder::class);

        $hrm = User::factory()->create();
        $hrm->assignRole('HRM Office II');
        $office = Office::factory()->create();
        $employee = User::factory()->create();
        $opcr = Opcr::factoryLike()->create();

        $this->actingAs($hrm)->get(route('offices.create'))->assertForbidden();
        $this->actingAs($hrm)->delete(route('offices.destroy', $office))->assertForbidden();
        $this->actingAs($hrm)->get(route('employees.edit', $employee))->assertForbidden();
        $this->actingAs($hrm)->delete(route('employees.destroy', $employee))->assertForbidden();
        $this->actingAs($hrm)->get(route('opcrs.create'))->assertForbidden();
        $this->actingAs($hrm)->patch(route('opcrs.approve', $opcr))->assertForbidden();
        $this->actingAs($hrm)->get(route('evaluations.create'))->assertForbidden();
        $this->actingAs($hrm)->get(route('performance-targets.create'))->assertForbidden();
    }
}
