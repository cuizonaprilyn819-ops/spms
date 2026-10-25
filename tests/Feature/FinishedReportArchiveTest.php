<?php

namespace Tests\Feature;

use App\Models\Ipcr;
use App\Models\Upcr;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishedReportArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_lists_only_approved_reports_and_keeps_employee_records_private(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $otherEmployee = User::factory()->create();
        $otherEmployee->assignRole('Employee');

        $upcr = Upcr::factoryLike()->create();
        $draftUpcr = Upcr::factoryLike()->create(['dpcr' => $upcr->dpcr]);
        $approved = Ipcr::factoryLike()->create(['upcr' => $upcr, 'employee' => $employee, 'title' => 'Finished personal IPCR', 'status' => Ipcr::STATUS_APPROVED]);
        $draft = Ipcr::factoryLike()->create(['upcr' => $draftUpcr, 'employee' => $employee, 'title' => 'Unfinished personal IPCR']);
        $other = Ipcr::factoryLike()->create(['upcr' => $upcr, 'employee' => $otherEmployee, 'title' => 'Other finished IPCR', 'status' => Ipcr::STATUS_APPROVED]);

        $this->actingAs($administrator)->get(route('reports.index'))
            ->assertOk()->assertSee('Finished personal IPCR')->assertSee('Other finished IPCR')
            ->assertDontSee('Unfinished personal IPCR');
        $this->get(route('reports.index', ['type' => 'ipcr']))
            ->assertOk()->assertSee('Finished personal IPCR')->assertDontSee('Unfinished personal IPCR');
        $this->get(route('reports.index', ['type' => 'invalid']))->assertNotFound();

        $this->actingAs($employee)->get(route('reports.index'))
            ->assertOk()->assertSee('Finished personal IPCR')
            ->assertSee('Unfinished personal IPCR')->assertDontSee('Other finished IPCR');

        $this->get(route('reports.finished', ['ipcr', $approved->id]))->assertOk()->assertSee('Finished personal IPCR');
        $this->get(route('reports.finished', ['ipcr', $draft->id]))->assertNotFound();
        $this->get(route('reports.finished.pdf', ['ipcr', $draft->id]))->assertNotFound();
        $this->get(route('reports.finished', ['ipcr', $other->id]))->assertNotFound();
        $this->get(route('reports.finished', ['upcr', $approved->upcr_id]))->assertForbidden();
    }

    public function test_finished_report_can_be_exported_individually(): void
    {
        $this->seed(RoleSeeder::class);
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $approved = Ipcr::factoryLike()->create(['employee' => $employee, 'title' => 'Approved export IPCR', 'status' => Ipcr::STATUS_APPROVED]);

        $pdf = $this->actingAs($employee)->get(route('reports.finished.pdf', ['ipcr', $approved->id]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $response = $this->actingAs($employee)->get(route('reports.finished.excel', ['ipcr', $approved->id]));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString('Approved export IPCR', $response->streamedContent());
    }

    public function test_hr_can_download_another_employees_finished_ipcr(): void
    {
        $this->seed(RoleSeeder::class);
        $hr = User::factory()->create();
        $hr->assignRole('HR');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $approved = Ipcr::factoryLike()->create([
            'employee' => $employee,
            'title' => 'Other employee approved IPCR',
            'status' => Ipcr::STATUS_APPROVED,
        ]);

        $this->actingAs($hr)->get(route('reports.finished', ['ipcr', $approved->id]))
            ->assertOk()->assertSee('Other employee approved IPCR');

        $pdf = $this->get(route('reports.finished.pdf', ['ipcr', $approved->id]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $excel = $this->get(route('reports.finished.excel', ['ipcr', $approved->id]));
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString('Other employee approved IPCR', $excel->streamedContent());
    }
}
