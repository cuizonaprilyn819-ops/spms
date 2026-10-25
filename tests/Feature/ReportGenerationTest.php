<?php

namespace Tests\Feature;

use App\Models\Accomplishment;
use App\Models\Evaluation;
use App\Models\Ipcr;
use App\Models\PerformanceTarget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_generate_all_report_types(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $forms = $this->sampleReportData();

        foreach (['opcr', 'dpcr', 'upcr', 'ipcr'] as $type) {
            $response = $this
                ->actingAs($user)
                ->get(route('reports.show', $type));

            $response->assertOk();
            $response->assertSee(strtoupper($type));
            $response->assertSee($forms[$type]->title);
        }
    }

    public function test_users_can_export_reports_as_pdf(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $this->sampleReportData();

        $response = $this
            ->actingAs($user)
            ->get(route('reports.pdf', 'ipcr'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_users_can_export_reports_as_excel(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $forms = $this->sampleReportData();

        $response = $this
            ->actingAs($user)
            ->get(route('reports.excel', 'ipcr'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename=ipcr-report-'.now()->format('Y-m-d').'.xls');
        $this->assertStringContainsString($forms['ipcr']->title, $response->streamedContent());
    }

    public function test_unknown_report_type_returns_not_found(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');

        $this
            ->actingAs($user)
            ->get(route('reports.show', 'unknown'))
            ->assertNotFound();
    }

    private function sampleReportData(): array
    {
        $employee = User::factory()->create(['name' => 'Report Employee']);
        $employee->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create([
            'employee' => $employee,
            'title' => 'Individual Quality Service Report',
        ]);
        $target = PerformanceTarget::factoryLike()->create([
            'targetable' => $ipcr,
            'created_by_user' => $employee,
            'target' => 'Submit client satisfaction report',
            'weight' => 20,
        ]);

        Accomplishment::factoryLike()->create([
            'performance_target' => $target,
            'submitted_by_user' => $employee,
            'completion_percentage' => 80,
        ]);

        Evaluation::create([
            'employee_id' => $employee->id,
            'performance_target_id' => $target->id,
            'quality_rating' => 5,
            'efficiency_rating' => 4,
            'timeliness_rating' => 4.5,
            'rating' => 4.5,
            'computed_rating' => Evaluation::computeRating(4.5, 20),
            'adjectival_rating' => 'Outstanding',
            'remarks' => 'Strong report data.',
            'rated_by' => User::factory()->create()->id,
        ]);

        return [
            'opcr' => $ipcr->upcr->dpcr->opcr,
            'dpcr' => $ipcr->upcr->dpcr,
            'upcr' => $ipcr->upcr,
            'ipcr' => $ipcr,
        ];
    }
}
