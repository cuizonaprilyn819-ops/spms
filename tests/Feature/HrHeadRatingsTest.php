<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Ipcr;
use App\Models\Office;
use App\Models\PerformanceTarget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrHeadRatingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_head_sees_employee_and_supervisor_ratings_across_offices(): void
    {
        $this->seed(RoleSeeder::class);
        $hrHead = User::factory()->create();
        $hrHead->assignRole('HR Head');
        $employee = User::factory()->create(['name' => 'Rated Employee', 'office_id' => Office::factory()->create()->id]);
        $employee->assignRole('Employee');
        $supervisor = User::factory()->create(['name' => 'Rated Supervisor', 'office_id' => Office::factory()->create()->id]);
        $supervisor->assignRole('Supervisor');

        $target = PerformanceTarget::factoryLike()->create();
        Evaluation::create([
            'employee_id' => $employee->id,
            'performance_target_id' => $target->id,
            'quality_rating' => 5,
            'efficiency_rating' => 4,
            'timeliness_rating' => 3,
            'rating' => 4,
            'computed_rating' => Evaluation::computeRating(4, (float) $target->weight),
            'adjectival_rating' => 'Very Satisfactory',
            'rated_by' => $supervisor->id,
        ]);

        $supervisorIpcr = Ipcr::create([
            'employee_id' => $supervisor->id,
            'prepared_by' => $supervisor->id,
            'approved_by' => $hrHead->id,
            'approved_at' => now(),
            'status' => Ipcr::STATUS_APPROVED,
            'title' => 'Supervisor Rated IPCR',
            'quality_rating' => 5,
            'efficiency_rating' => 5,
            'timeliness_rating' => 4,
            'average_rating' => 4.67,
        ]);
        $unratedIpcr = Ipcr::create([
            'employee_id' => $employee->id,
            'prepared_by' => $employee->id,
            'approved_by' => $hrHead->id,
            'approved_at' => now(),
            'status' => Ipcr::STATUS_APPROVED,
            'title' => 'Approved Without Scores',
        ]);

        $this->actingAs($hrHead)->get(route('ratings.index'))
            ->assertOk()
            ->assertSee('Rated Employee')
            ->assertSee('Rated Supervisor')
            ->assertSee('Supervisor Rated IPCR')
            ->assertSee('Approved Without Scores')
            ->assertSee('Not rated')
            ->assertSee('1 approved without scores')
            ->assertSee('4.67')
            ->assertSee(route('evaluations.show', Evaluation::firstOrFail()))
            ->assertSee(route('ipcrs.show', $supervisorIpcr))
            ->assertSee(route('ipcrs.show', $unratedIpcr));

        $this->get(route('ratings.index', ['role' => 'Supervisor']))
            ->assertOk()
            ->assertDontSee('Rated Employee')
            ->assertDontSee('Approved Without Scores')
            ->assertSee('Rated Supervisor');

        $this->get(route('ratings.index', ['role' => 'Employee']))
            ->assertOk()
            ->assertSee('Rated Employee')
            ->assertSee('Approved Without Scores')
            ->assertDontSee('Supervisor Rated IPCR');

        $this->get(route('ratings.index', ['role' => 'Employee']))
            ->assertSee(route('ratings.pdf', ['role' => 'Employee']));

        $pdf = $this->get(route('ratings.pdf', ['role' => 'Employee']));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_hr_can_export_employee_ratings_as_pdf(): void
    {
        $this->seed(RoleSeeder::class);
        $hr = User::factory()->create();
        $hr->assignRole('HR');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        Ipcr::create([
            'employee_id' => $employee->id,
            'prepared_by' => $employee->id,
            'approved_by' => $hr->id,
            'approved_at' => now(),
            'status' => Ipcr::STATUS_APPROVED,
            'title' => 'Employee rated IPCR',
            'quality_rating' => 5,
            'efficiency_rating' => 4,
            'timeliness_rating' => 3,
            'average_rating' => 4,
        ]);

        $this->actingAs($hr)->get(route('ratings.index', ['role' => 'Employee']))
            ->assertOk()->assertSee('Employee rated IPCR')->assertSee(route('ratings.pdf', ['role' => 'Employee']));

        $pdf = $this->get(route('ratings.pdf', ['role' => 'Employee']));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $pdf->assertHeader('content-disposition', 'attachment; filename=employee-ratings-'.now()->format('Y-m-d').'.pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_other_roles_cannot_open_hr_head_ratings(): void
    {
        $this->seed(RoleSeeder::class);
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $this->actingAs($employee)->get(route('ratings.index'))->assertForbidden();
        $this->get(route('ratings.pdf'))->assertForbidden();
    }
}
