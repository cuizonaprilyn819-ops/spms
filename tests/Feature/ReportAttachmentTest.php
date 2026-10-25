<?php

namespace Tests\Feature;

use App\Models\{Office, User, Opcr, Dpcr, Upcr, Ipcr, ReportAttachment};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_attach_and_download_files_for_each_report_type(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();
        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Supervisor');
        $opcr = Opcr::factoryLike()->create(['office' => $office]);
        $dpcr = Dpcr::factoryLike()->create(['opcr' => $opcr, 'employee' => $user]);
        $upcr = Upcr::factoryLike()->create(['dpcr' => $dpcr, 'employee' => $user]);
        $ipcr = Ipcr::factoryLike()->create(['upcr' => $upcr, 'employee' => $user]);

        foreach (compact('opcr', 'dpcr', 'upcr', 'ipcr') as $type => $report) {
            $this->actingAs($user)->get(route($type.'s.show', $report))->assertOk()->assertSee('Upload File');
            $this->post(route('report-attachments.store', [$type, $report->id]), [
                'file' => UploadedFile::fake()->create('target.pdf', 100, 'application/pdf'),
            ])->assertRedirect(route($type.'s.show', $report));
            $attachment = ReportAttachment::where('report_type', $type)->sole();
            $this->assertEquals($report->id, $attachment->report_id);
            Storage::disk('local')->assertExists($attachment->path);
            $this->get(route('report-attachments.download', $attachment))->assertDownload('target.pdf');
        }

        $outsider = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $outsider->assignRole('Supervisor');
        $this->actingAs($outsider)->post(route('report-attachments.store', ['opcr', $opcr->id]), [
            'file' => UploadedFile::fake()->create('target.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
        $this->get(route('report-attachments.download', $attachment))->assertForbidden();

        $this->actingAs($user)->post(route('report-attachments.store', ['opcr', $opcr->id]), [
            'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors('file');
        $this->post(route('report-attachments.store', ['opcr', $opcr->id]), [
            'file' => UploadedFile::fake()->create('script.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('report_attachments', 4);

        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $this->actingAs($employee)->post(route('report-attachments.store', ['opcr', $opcr->id]))->assertForbidden();
    }
}
