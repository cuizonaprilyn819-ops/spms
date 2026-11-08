<?php

namespace Tests\Feature;

use App\Models\{Ipcr, Mov, PerformanceTarget, User};
use App\Services\IpcrPdfFormat;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrSchoolPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipcr_pdf_uses_legal_landscape_school_layout_for_draft_and_finished_exports(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['name' => 'Sample Employee']);
        $user->assignRole('Employee');
        $report = Ipcr::factoryLike()->create(['employee' => $user, 'prepared_by_user' => $user]);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $report,
            'success_indicator' => 'Conduct 1 preventive maintenance activity within the rating period.']);
        $target->update(['scope_metadata' => ['category' => 'Core Functions']]);
        $response = $this->actingAs($user)->get(route('reports.pdf', 'ipcr'));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('/MediaBox [0.000 0.000 1008.000 612.000]', $response->getContent());
        if (getenv('SPMS_PDF_QA_OUTPUT')) {
            file_put_contents(getenv('SPMS_PDF_QA_OUTPUT'), $response->getContent());
        }
        $report->update(['status' => Ipcr::STATUS_APPROVED, 'approved_by' => $user->id, 'approved_at' => now()]);
        $finished = app(\App\Http\Controllers\ReportController::class)->finishedPdf('ipcr', $report->id);
        $this->assertStringContainsString('/MediaBox [0.000 0.000 1008.000 612.000]', $finished->getContent());
    }

    public function test_format_uses_saved_weights_and_scopes_evidence_to_employee_without_inventing_ratings(): void
    {
        $this->seed(RoleSeeder::class);
        $report = Ipcr::factoryLike()->create();
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $report, 'weight' => 20]);
        $target->update(['scope_metadata' => ['category' => 'Core Functions']]);
        foreach ([$report->employee_id => 'Own evidence.pdf', User::factory()->create()->id => 'Private other evidence.pdf'] as $owner => $name) {
            Mov::create(['performance_target_id' => $target->id, 'title' => 'Evidence', 'file_path' => 'movs/sample.pdf',
                'original_name' => $name, 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'file_size' => 10, 'uploaded_by' => $owner]);
        }
        $data = app(IpcrPdfFormat::class)->data($report, collect([$target]), [['Indicator', '', '', '', '', '', '', '']]);
        $this->assertSame(20.0, (float) $data['weights']['Core Functions']);
        $this->assertSame('', $data['average']);
        $this->assertSame(['Own evidence.pdf'], array_column($data['evidence'], 'name'));
        $target->update(['scope_metadata' => null]);
        $unknown = app(IpcrPdfFormat::class)->data($report, collect([$target]), [['Indicator', '', '', '', '', '', '', '']]);
        $this->assertCount(1, $unknown['sections']['Unclassified Targets']);
    }
}
