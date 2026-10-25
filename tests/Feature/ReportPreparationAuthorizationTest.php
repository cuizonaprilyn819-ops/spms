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

    public function test_each_role_can_only_open_its_own_report_preparation_form(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();

        $users = collect(['Campus Director', 'Office Head', 'Supervisor', 'Employee', 'Administrator'])
            ->mapWithKeys(function (string $role) use ($office): array {
                $user = User::factory()->create(['office_id' => $role === 'Administrator' ? null : $office->id]);
                $user->assignRole($role);

                return [$role => $user];
            });

        $allowed = [
            'Campus Director' => 'opcrs.create',
            'Office Head' => 'dpcrs.create',
            'Supervisor' => 'upcrs.create',
            'Employee' => 'ipcrs.create',
        ];

        foreach ($allowed as $role => $route) {
            $this->actingAs($users[$role])->get(route($route))->assertOk();

            foreach (array_diff(array_values($allowed), [$route]) as $forbiddenRoute) {
                $this->actingAs($users[$role])->get(route($forbiddenRoute))->assertForbidden();
            }
        }

        foreach ($allowed as $route) {
            $this->actingAs($users['Administrator'])->get(route($route))->assertForbidden();
        }
    }
}
