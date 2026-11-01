<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\PerformanceTarget;
use App\Models\TargetScopeDraft;
use App\Models\User;
use App\Services\CommitmentPlanning;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PlanningConfirmationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_processes_confirming_the_same_draft_create_only_one_target(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Cross-process locking is verified against disposable PostgreSQL.');
        }
        $this->seed(RoleSeeder::class);
        $reviewer = User::factory()->create();
        $reviewer->assignRole('Administrator');
        $root = Office::factory()->activeForCampus()->create(['planning_level' => 'office', 'mandate' => 'International exchanges', 'hierarchy_verified_at' => now(), 'planning_reviewer_id' => $reviewer->id]);
        $head = User::factory()->create(['office_id' => $root->id]);
        $root->update(['head_id' => $head->id]);
        $department = Office::factory()->activeForCampus()->create(['planning_level' => 'division', 'parent_office_id' => $root->id, 'mandate' => 'Arrange student exchanges', 'hierarchy_verified_at' => now(), 'planning_reviewer_id' => $reviewer->id]);
        $departmentHead = User::factory()->create(['office_id' => $department->id]);
        $department->update(['head_id' => $departmentHead->id]);
        $cycle = EvaluationCycle::create(['year' => 2027, 'semester' => '1st Semester', 'status' => 'open', 'planning_enabled' => true]);
        $plan = app(CommitmentPlanning::class)->createPlan($head, $cycle, $root->fresh(), 'opcr', null);
        $draft = TargetScopeDraft::create(['commitment_plan_id' => $plan->id, 'plan_revision' => 1, 'created_by' => $head->id,
            'payload' => ['target' => 'Deploy exchange students', 'success_indicator' => 'Approved deployments', 'measurement' => 'count', 'quantity' => 2, 'whole_numbers' => true, 'unit' => 'students', 'standards' => 'Approved before deadline', 'deadline' => '2027-06-30', 'source_evidence' => 'Reviewed mobility plan', 'weight' => 25, 'coverage' => 'departments', 'recipient_ids' => [$department->id], 'quantity_basis' => 'total', 'matching_reason' => 'Verified mobility mandate']]);
        $connection = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'DB_SSLMODE' => 'disable', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'SPMS_AI_ENABLED' => 'false'];
        $code = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); app(App\\Services\\GuidedTargetPlanning::class)->confirm(App\\Models\\TargetScopeDraft::findOrFail('.$draft->id.'), App\\Models\\User::findOrFail('.$head->id.')); echo "confirmed";';
        $processes = [new Process([PHP_BINARY, '-d', 'extension=pdo_pgsql', '-r', $code], base_path(), $env), new Process([PHP_BINARY, '-d', 'extension=pdo_pgsql', '-r', $code], base_path(), $env)];
        foreach ($processes as $process) {
            $process->start();
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringContainsString('confirmed', $process->getOutput());
        }
        $this->assertSame(1, PerformanceTarget::where('commitment_plan_id', $plan->id)->count());
        $this->assertEquals(2, PerformanceTarget::sole()->quantity);
        $this->assertNotNull($draft->fresh()->performance_target_id);
    }
}
