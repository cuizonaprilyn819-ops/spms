<?php
namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, TargetMov, User};
use App\Support\TargetActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_keeps_upload_and_completion_history_in_the_correct_scope(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $office = Office::factory()->create();
        $director = User::factory()->create(); $director->assignRole('Campus Director');
        $otherDirector = User::factory()->create(); $otherDirector->assignRole('Campus Director');
        $head = User::factory()->create(['office_id' => $office->id]); $head->assignRole('HR Head');
        $otherHead = User::factory()->create(['office_id' => Office::factory()->create()->id]); $otherHead->assignRole('Supervisor');
        $admin = User::factory()->create(); $admin->assignRole('Administrator');
        $employee = User::factory()->create(['office_id' => $office->id]); $employee->assignRole('Employee');
        $target = OfficeTarget::create(['title' => 'Completed training', 'success_indicator' => 'One session',
            'target_value' => 1, 'unit_of_measure' => 'session', 'weight' => 20,
            'deadline' => '2026-12-31', 'priority' => 'high', 'created_by' => $director->id]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 1]);
        $target->assignedEmployees()->attach($employee->id, ['assigned_by' => $head->id, 'allocated_target_value' => 1, 'accepted_at' => now()->subDay(), 'completed_at' => now()]);
        foreach (['attendance.pdf', 'report.pdf'] as $filename) {
            TargetMov::create(['office_target_id' => $target->id, 'user_id' => $employee->id, 'office_id' => $office->id,
                'accomplishment' => 'Session delivered', 'path' => $filename, 'original_name' => $filename]);
        }
        $log = app(TargetActivityLog::class);
        // Upload activity must appear before the assignee declares completion.
        $target->assignedEmployees()->updateExistingPivot($employee->id, ['completed_at' => null]);
        $supervisor = User::factory()->create(['office_id' => $office->id]);
        $supervisor->assignRole('Supervisor');
        foreach ([$director, $head, $supervisor, $admin] as $viewer) {
            $events = $log->forUser($viewer);
            $this->assertSame(2, $events->total());
            $this->assertSame(['MOV attached'], collect($events->items())->pluck('action')->unique()->values()->all());
            $this->actingAs($viewer)->get(route('dashboard'))->assertOk()
                ->assertSee('attendance.pdf')->assertSee('report.pdf')->assertDontSee('Marked complete');
        }
        $this->assertNull($target->assignedEmployees()->whereKey($employee->id)->first()->pivot->completed_at);
        $target->assignedEmployees()->updateExistingPivot($employee->id, ['completed_at' => now()]);
        foreach ([$director, $head, $admin] as $viewer) {
            $this->assertSame(3, $log->forUser($viewer)->total());
            $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertSee('Target Activity Log')
                ->assertSee('attendance.pdf')->assertSee('report.pdf')->assertSee('Marked complete');
        }
        foreach ([$otherDirector, $otherHead] as $viewer) {
            $this->assertSame(0, $log->forUser($viewer)->total());
        }
        $this->assertNull($log->forUser($employee));
        // Log visibility does not depend on unread notification records.
        $director->notifications()->delete();
        $this->assertSame(3, $log->forUser($director)->total());
    }
}
