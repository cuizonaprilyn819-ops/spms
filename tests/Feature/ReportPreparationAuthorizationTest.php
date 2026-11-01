<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPreparationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_preparation_respects_current_roles_and_office_assignment(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();

        $users = collect(['Campus Director', 'HR Head', 'Supervisor', 'Employee', 'Administrator', 'HR', 'HRM Office II', 'Planning Officer'])
            ->mapWithKeys(function (string $role) use ($office): array {
                $user = User::factory()->create(['office_id' => $role === 'Administrator' ? null : $office->id]);
                $user->assignRole($role);

                return [$role => $user];
            });

        $allowed = [
            'Campus Director' => ['opcrs.create', 'ipcrs.create'],
            'HR Head' => ['dpcrs.create', 'upcrs.create', 'ipcrs.create'],
            'Supervisor' => ['dpcrs.create', 'upcrs.create', 'ipcrs.create'],
            'Employee' => ['ipcrs.create'],
            'Administrator' => [], // An administrator without an office cannot prepare a personal IPCR.
            'HR' => ['ipcrs.create'],
            'HRM Office II' => ['ipcrs.create'],
            'Planning Officer' => ['ipcrs.create'],
        ];

        foreach ($allowed as $role => $routes) {
            foreach (['opcrs.create', 'dpcrs.create', 'upcrs.create', 'ipcrs.create'] as $route) {
                $response = $this->actingAs($users[$role])->get(route($route));
                in_array($route, $routes, true) ? $response->assertOk() : $response->assertForbidden();
            }
        }
    }
}
