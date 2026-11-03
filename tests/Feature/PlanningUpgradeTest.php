<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\Office;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanningUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_additive_upgrade_preserves_legacy_records_and_can_be_reapplied(): void
    {
        // Remove only the incoming adviser's additions in this disposable database.
        // The local MOV/report-context migrations must remain applied.
        Artisan::call('migrate:rollback', ['--path' => [
            'database/migrations/2026_10_30_000001_create_historical_pcr_archive.php',
            'database/migrations/2026_11_01_180000_add_multi_campus_target_planning.php',
            'database/migrations/2026_11_02_000001_create_target_assistance_tables.php',
        ], '--force' => true]);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('target_assistances'));
        $office = DB::table('offices')->insertGetId(['name' => 'Retained legacy office', 'code' => 'LEGACY']);
        $user = DB::table('users')->insertGetId(['name' => 'Retained employee', 'email' => 'retained@example.test', 'password' => 'unused', 'office_id' => $office]);
        $cycle = DB::table('evaluation_cycles')->insertGetId(['year' => 2024, 'semester' => '1st Semester', 'status' => 'closed']);
        DB::table('opcrs')->insert(['office_id' => $office, 'evaluation_cycle_id' => $cycle, 'title' => 'Retained approved report', 'status' => 'approved', 'prepared_by' => $user, 'targets' => 'Original targets']);
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('migrate', ['--force' => true]);
        $this->assertDatabaseHas('opcrs', ['title' => 'Retained approved report', 'status' => 'approved', 'targets' => 'Original targets']);
        $this->assertFalse(EvaluationCycle::findOrFail($cycle)->planning_enabled);
        $this->assertFalse(EvaluationCycle::findOrFail($cycle)->multi_campus_enabled);
        $this->assertSame('legacy', Office::findOrFail($office)->organization_scope);
        $this->assertNull(Office::findOrFail($office)->planning_level);
        $this->assertDatabaseCount('commitment_plans', 0);
    }
}
