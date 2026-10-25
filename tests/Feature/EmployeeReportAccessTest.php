<?php
namespace Tests\Feature;

use App\Models\{User, Office};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_has_only_ipcr_report_access(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $employee = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $employee->assignRole('Employee');
        $this->actingAs($employee)->get(route('dashboard'))->assertOk()
            ->assertSee('My Reports')
            ->assertDontSee('href="'.route('ipcrs.index').'"', false)
            ->assertDontSee('href="'.route('upcrs.index').'"', false);
        foreach (['opcrs', 'dpcrs', 'upcrs'] as $resource) {
            $this->get(route($resource.'.index'))->assertForbidden();
            $this->get(route($resource.'.create'))->assertForbidden();
            $this->post(route($resource.'.store'), [])->assertForbidden();
        }
        $this->get(route('ipcrs.create'))->assertOk();
        $this->get(route('reports.index'))->assertOk()->assertSee('Prepare IPCR')->assertDontSee('Prepare OPCR');
        foreach (['reports.show', 'reports.pdf', 'reports.excel'] as $route) {
            $this->get(route($route, 'upcr'))->assertForbidden();
        }
        $upcr = \App\Models\Upcr::factoryLike()->create();
        \App\Models\Ipcr::factoryLike()->create(['upcr' => $upcr, 'employee' => $employee, 'title' => 'My personal commitment']);
        \App\Models\Ipcr::factoryLike()->create(['upcr' => $upcr, 'title' => 'Another employee private commitment']);
        $this->get(route('reports.index'))->assertOk()
            ->assertSee('My personal commitment')
            ->assertDontSee('Another employee private commitment');
        $this->get(route('reports.show', 'ipcr'))->assertOk()
            ->assertSee('My personal commitment')->assertDontSee('Another employee private commitment');
    }

    public function test_my_reports_shows_the_forms_users_prepare_for_their_roles(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $office = Office::factory()->create();

        foreach ([
            'Employee' => ['IPCR'],
            'HR' => ['IPCR'],
            'Campus Director' => ['OPCR', 'IPCR'],
            'HR Head' => ['DPCR', 'IPCR'],
            'Supervisor' => ['DPCR', 'UPCR', 'IPCR'],
        ] as $role => $types) {
            $user = User::factory()->create(['office_id' => $office->id]);
            $user->assignRole($role);
            $response = $this->actingAs($user)->get(route('reports.index'))->assertOk()
                ->assertSee('My report forms');

            foreach (['OPCR', 'DPCR', 'UPCR', 'IPCR'] as $type) {
                if (in_array($type, $types, true)) {
                    $response->assertSee('Prepare '.$type);
                } else {
                    $response->assertDontSee('Prepare '.$type);
                }
            }

            $navigation = $this->get(route('dashboard'))->assertOk();
            foreach (['opcrs', 'dpcrs', 'upcrs', 'ipcrs'] as $resource) {
                $navigation->assertDontSee('href="'.route($resource.'.index').'"', false);
            }
        }
    }
}
