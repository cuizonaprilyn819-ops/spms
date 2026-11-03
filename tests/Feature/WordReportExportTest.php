<?php

namespace Tests\Feature;

use App\Models\{Ipcr, PerformanceTarget, ReportGeneration, User};
use App\Services\WordReport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function documentXml(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'word-report-');
        unlink($path);
        $path .= '.zip';
        try {
            file_put_contents($path, $bytes);
            $zip = new \PharData($path);
            $this->assertTrue(isset($zip['[Content_Types].xml']));
            $this->assertTrue(isset($zip['_rels/.rels']));
            $this->assertTrue(isset($zip['word/styles.xml']));
            $xml = $zip['word/document.xml']->getContent();
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xml));
            unset($zip);
            return $xml;
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    public function test_all_report_types_export_real_word_documents_and_log_the_format(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create()->assignRole('Administrator');
        $ipcr = Ipcr::factoryLike()->create(['title' => 'Editable report & review']);
        PerformanceTarget::factoryLike()->create(['targetable' => $ipcr, 'target' => 'Maintenance', 'success_indicator' => 'Maintenance <review> & café']);
        $this->actingAs($admin);
        foreach (['opcr', 'dpcr', 'upcr', 'ipcr'] as $type) {
            $this->get(route('reports.show', $type))->assertOk()->assertSee('Export Word (.docx)');
            $response = $this->get(route('reports.word', $type))->assertOk()->assertHeader('Content-Type', WordReport::MIME);
            $this->assertStringContainsString('.docx', $response->headers->get('Content-Disposition'));
            $xml = $this->documentXml($response->getContent());
            $this->assertStringContainsString('<w:tbl>', $xml);
            $this->assertStringContainsString('<w:tblHeader/>', $xml);
            if ($type === 'ipcr') {
                $this->assertStringContainsString('Maintenance &lt;review&gt; &amp; café', $xml);
                $this->assertStringContainsString('Editable report &amp; review', $xml);
            }
        }
        $this->assertSame(4, ReportGeneration::where('format', 'word')->count());
    }

    public function test_employee_exports_only_their_own_records_and_finished_exports_require_approval(): void
    {
        $this->seed(RoleSeeder::class);
        $employee = User::factory()->create()->assignRole('Employee');
        $own = Ipcr::factoryLike()->create(['employee' => $employee, 'title' => 'My editable report', 'status' => 'approved']);
        $other = Ipcr::factoryLike()->create(['upcr' => $own->upcr, 'title' => 'Private other report', 'status' => 'approved']);
        $draftUpcr = \App\Models\Upcr::factoryLike()->create(['dpcr' => $own->upcr->dpcr]);
        $draft = Ipcr::factoryLike()->create(['upcr' => $draftUpcr, 'employee' => $employee, 'title' => 'My draft']);
        $xml = $this->documentXml($this->actingAs($employee)->get(route('reports.word', 'ipcr'))->assertOk()->getContent());
        $this->assertStringContainsString('My editable report', $xml);
        $this->assertStringNotContainsString('Private other report', $xml);
        $this->get(route('reports.finished.word', ['ipcr', $own->id]))->assertOk();
        $entry = ReportGeneration::latest('id')->first();
        $this->assertSame('word', $entry->format);
        $this->assertSame([$own->id], $entry->record_ids);
        $this->get(route('reports.finished.word', ['ipcr', $other->id]))->assertNotFound();
        $this->get(route('reports.finished.word', ['ipcr', $draft->id]))->assertNotFound();
        $this->get(route('reports.word', 'upcr'))->assertForbidden();
        $this->get(route('reports.word', 'invalid'))->assertNotFound();
        $this->assertSame(2, ReportGeneration::where('format', 'word')->count());
    }
}
