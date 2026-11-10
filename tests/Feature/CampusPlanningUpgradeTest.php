<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\Opcr;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CampusPlanningUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_upgrade_preserves_existing_accounts_reports_and_period_workflows(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        $office = DB::table('offices')->insertGetId(['name' => 'Retained University', 'code' => 'RETAINED', 'organization_scope' => 'university']);
        $user = DB::table('users')->insertGetId(['name' => 'Retained owner', 'email' => 'retained-owner@example.test', 'password' => 'retained-hash', 'office_id' => $office]);
        $cycle = DB::table('evaluation_cycles')->insertGetId(['year' => 2025, 'semester' => '1st Semester', 'status' => 'closed', 'planning_enabled' => true,
            'multi_campus_enabled' => true, 'university_office_id' => $office]);
        $report = DB::table('opcrs')->insertGetId(['office_id' => $office, 'evaluation_cycle_id' => $cycle, 'title' => 'Retained signed report', 'status' => 'approved', 'prepared_by' => $user, 'targets' => 'Original commitments and ratings']);
        DB::table('commitment_plans')->insert(['report_type' => Opcr::class, 'report_id' => $report, 'evaluation_cycle_id' => $cycle,
            'office_id' => $office, 'prepared_by' => $user, 'reviewer_id' => $user, 'level' => 'opcr', 'state' => 'approved']);
        $before = [];
        foreach (['users', 'offices', 'opcrs', 'commitment_plans'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toArray();
        }
        Artisan::call('migrate', ['--force' => true]);
        foreach ($before as $table => $rows) {
            $this->assertEquals($rows, DB::table($table)->orderBy('id')->get()->toArray());
        }
        $period = EvaluationCycle::findOrFail($cycle);
        $this->assertNull($period->planning_root_level);
        $this->assertTrue($period->usesUniversityOpcr());
        $this->assertSame('closed', $period->status);
        $this->assertEquals($office, $period->university_office_id);
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(EvaluationCycle::findOrFail($cycle)->usesUniversityOpcr());
        $this->assertDatabaseHas('opcrs', ['id' => $report, 'targets' => 'Original commitments and ratings', 'status' => 'approved']);
    }
}
