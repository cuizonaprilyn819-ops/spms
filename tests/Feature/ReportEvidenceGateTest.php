<?php

namespace Tests\Feature;

use App\Models\{Ipcr, Mov, Office, PerformanceTarget, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportEvidenceGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_target_ipcr_requires_its_own_employees_analyzed_mov(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $employee = User::factory()->create(['office_id' => Office::factory()->create()->id])->assignRole('Employee');
        $target = \App\Models\OfficeTarget::create(['title' => 'Maintenance', 'success_indicator' => '1 maintenance conducted', 'target_value' => 1, 'unit_of_measure' => 'maintenance', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $employee->id]);
        $report = Ipcr::create(['office_target_id' => $target->id, 'employee_id' => $employee->id, 'prepared_by' => $employee->id, 'title' => 'Maintenance IPCR', 'status' => 'draft']);
        $url = route('report-workflow.submit', ['ipcr', $report]);
        $this->actingAs($employee)->patch($url)->assertSessionHasErrors('mov');
        Storage::disk('local')->put('maintenance.pdf', '%PDF fixture');
        $mov = \App\Models\TargetMov::create(['office_target_id' => $target->id, 'user_id' => $employee->id, 'office_id' => $employee->office_id, 'accomplishment' => 'Maintenance done', 'path' => 'maintenance.pdf', 'original_name' => 'maintenance.pdf']);
        $this->patch($url)->assertSessionHasErrors('mov');
        $analysis = $mov->analysis()->create(['status' => 'completed', 'findings' => ['supported' => true]]);
        $this->patch($url)->assertSessionHasErrors('mov');
        $analysis->update(['findings' => ['supported' => true, 'satisfies_target' => true]]);
        $this->patch($url)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('submitted', $report->fresh()->status);
    }

    public function test_missing_pending_partial_failed_and_other_users_mov_cannot_submit(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        $employee = User::factory()->create(['office_id' => Office::factory()->create()->id])->assignRole('Employee');
        $report = Ipcr::factoryLike()->create(['employee' => $employee, 'prepared_by_user' => $employee]);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $report]);
        $url = route('report-workflow.submit', ['ipcr', $report]);
        $this->actingAs($employee)->patch($url)->assertSessionHasErrors('mov');
        Storage::disk('public')->put('proof.pdf', '%PDF fixture');
        $mov = Mov::create(['performance_target_id' => $target->id, 'uploaded_by' => User::factory()->create()->id, 'title' => 'MOV', 'file_path' => 'proof.pdf', 'original_name' => 'proof.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'file_size' => 12]);
        $analysis = $mov->analysis()->create(['status' => 'completed', 'findings' => ['supported' => true, 'satisfies_target' => true]]);
        $this->patch($url)->assertSessionHasErrors('mov');
        $mov->update(['uploaded_by' => $employee->id]);
        foreach (['analyzing', 'failed', 'completed'] as $status) {
            $analysis->update(['status' => $status, 'findings' => ['supported' => true, 'satisfies_target' => false]]);
            $this->patch($url)->assertSessionHasErrors('mov');
        }
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertSame(0, $report->statusHistories()->count());
        $this->assertDatabaseCount('pcr_submissions', 0);
        $analysis->update(['status' => 'completed', 'findings' => ['supported' => true, 'satisfies_target' => true]]);
        $this->patch($url)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('submitted', $report->fresh()->status);
        $report->update(['status' => 'returned']);
        Storage::disk('public')->delete('proof.pdf');
        $this->patch($url)->assertSessionHasErrors('mov');
        $this->assertSame('returned', $report->fresh()->status);
    }
}
