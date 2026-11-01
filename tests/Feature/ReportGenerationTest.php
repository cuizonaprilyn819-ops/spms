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

    public function test_employee_can_generate_unapproved_ipcr_with_blank_accomplishments_and_audited_snapshots(): void
    {
        $this->seed(RoleSeeder::class);
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee, 'title' => 'Ongoing quarter', 'status' => 'draft']);
        PerformanceTarget::factoryLike()->create(['targetable' => $ipcr, 'created_by_user' => $employee]);
        $other = Ipcr::factoryLike()->create(['upcr' => $ipcr->upcr, 'title' => 'Private other report']);

        $this->actingAs($employee)->get(route('reports.show', 'ipcr'))->assertOk()->assertSee('Export PDF');
        $entry = \App\Models\ReportGeneration::sole();
        $this->assertSame([$ipcr->id], $entry->record_ids);
        $this->assertSame('', $entry->snapshot['forms'][0]['rows'][0][2]);
        $this->assertSame($employee->id, $entry->generated_by);
        $this->assertSame('preview', $entry->format);
        $this->get(route('reports.pdf', 'ipcr'))->assertOk();
        $this->get(route('reports.excel', 'ipcr'))->assertOk();
        $this->assertSame(['preview', 'pdf', 'excel'], \App\Models\ReportGeneration::orderBy('id')->pluck('format')->all());
        $this->get(route('reports.index'))->assertOk()->assertSee('Generation history / Audit log');
        $this->assertDatabaseCount('report_generations', 3);
        $this->actingAs($other->employee)->get(route('reports.index'))->assertOk()->assertSee('No reports generated yet.');
        $this->actingAs($employee)->get(route('reports.pdf', 'opcr'))->assertForbidden();
        $this->assertDatabaseCount('report_generations', 3);
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
