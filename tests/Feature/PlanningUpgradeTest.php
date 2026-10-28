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
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        $office = DB::table('offices')->insertGetId(['name' => 'Retained legacy office', 'code' => 'LEGACY']);
        $user = DB::table('users')->insertGetId(['name' => 'Retained employee', 'email' => 'retained@example.test', 'password' => 'unused', 'office_id' => $office]);
        $cycle = DB::table('evaluation_cycles')->insertGetId(['year' => 2024, 'semester' => '1st Semester', 'status' => 'closed']);
        DB::table('opcrs')->insert(['office_id' => $office, 'evaluation_cycle_id' => $cycle, 'title' => 'Retained approved report', 'status' => 'approved', 'prepared_by' => $user, 'targets' => 'Original targets']);
        Artisan::call('migrate', ['--force' => true]);
        $this->assertDatabaseHas('opcrs', ['title' => 'Retained approved report', 'status' => 'approved', 'targets' => 'Original targets']);
        $this->assertFalse(EvaluationCycle::findOrFail($cycle)->planning_enabled);
        $this->assertNull(Office::findOrFail($office)->planning_level);
        $this->assertDatabaseCount('commitment_plans', 0);
    }
}
