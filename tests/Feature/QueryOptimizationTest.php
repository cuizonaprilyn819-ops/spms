<?php

namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, TargetMov, User};
use App\Services\TargetHierarchy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class QueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deep_hierarchy_uses_one_small_office_query_and_preserves_campus_boundaries(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->activeForCampus()->create();
        $head = User::factory()->create(['office_id' => $office->id])->assignRole('Supervisor');
        $ids = [$office->id];
        foreach (range(1, 5) as $level) {
            $office = Office::factory()->activeForCampus()->create(['parent_office_id' => $office->id]);
            $ids[] = $office->id;
        }
        Office::factory()->create(['campus' => 99, 'parent_office_id' => $office->id]);
        $head->load(['office', 'roles']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertEqualsCanonicalizing($ids, app(TargetHierarchy::class)->managedOfficeIds($head));
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "offices"'));
        $this->assertCount(1, $queries);
        $this->assertStringNotContainsString('select *', $queries->sole()['query']);
    }

    public function test_mov_history_is_paginated_and_analysis_queries_do_not_grow_per_file(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->activeForCampus()->create();
        $user = User::factory()->create(['office_id' => $office->id])->assignRole('Employee');
        $other = User::factory()->create(['office_id' => $office->id])->assignRole('Employee');
        $target = OfficeTarget::create(['title' => 'Maintenance', 'success_indicator' => '1 maintenance activity', 'target_value' => 1,
            'unit_of_measure' => 'activity', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $user->id]);
        $target->offices()->attach($office, ['allocated_target_value' => 1]);
        $target->assignedEmployees()->attach($user, ['assigned_by' => $user->id, 'accepted_at' => now(), 'allocated_target_value' => 1]);
        foreach (range(1, 23) as $i) {
            $mov = TargetMov::create(['office_target_id' => $target->id, 'user_id' => $user->id, 'office_id' => $office->id,
                'accomplishment' => 'Activity record', 'path' => "record-$i.pdf", 'original_name' => "record-$i.pdf"]);
            $mov->analysis()->create(['status' => 'completed', 'summary' => 'Needs supporting evidence.', 'findings' => ['supported' => false, 'satisfies_target' => false]]);
        }
        TargetMov::create(['office_target_id' => $target->id, 'user_id' => $other->id, 'office_id' => $office->id,
            'accomplishment' => 'Private other employee record', 'path' => 'other-private.pdf', 'original_name' => 'other-private.pdf']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($user)->get(route('office-targets.show', $target))->assertOk()
            ->assertSee('record-23.pdf')->assertSee('record-14.pdf')->assertDontSee('record-13.pdf')->assertDontSee('other-private.pdf')
            ->assertSee('target_mov_page=2');
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "evidence_analyses"'));
        $this->assertCount(1, $queries);
        $this->get(route('office-targets.show', [$target, 'target_mov_page' => 3]))->assertOk()
            ->assertSee('record-3.pdf')->assertSee('record-1.pdf')->assertDontSee('record-4.pdf')->assertDontSee('other-private.pdf');
        $this->assertDatabaseCount('target_movs', 24);
    }

    public function test_lookup_indexes_are_additive_reversible_and_used_for_mov_owner_lookup(): void
    {
        $migration = require database_path('migrations/2026_11_08_160000_add_mov_and_notification_lookup_indexes.php');
        User::factory()->create(['name' => 'Retained fixture']);
        $before = DB::table('users')->count();
        $this->assertTrue(Schema::hasIndex('target_movs', 'target_mov_owner_timeline'));
        $this->assertTrue(Schema::hasIndex('notifications', 'notification_recipient_unread'));
        $plan = DB::select('EXPLAIN QUERY PLAN SELECT * FROM target_movs WHERE office_target_id = ? AND user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10', [1, 1]);
        $this->assertStringContainsString('target_mov_owner_timeline', json_encode($plan));
        $migration->down();
        $this->assertFalse(Schema::hasIndex('target_movs', 'target_mov_owner_timeline'));
        $migration->up();
        $this->assertTrue(Schema::hasIndex('notifications', 'notification_recipient_timeline'));
        $this->assertSame($before, DB::table('users')->count());
        $this->assertSame('Retained fixture', DB::table('users')->sole()->name);
    }
}
