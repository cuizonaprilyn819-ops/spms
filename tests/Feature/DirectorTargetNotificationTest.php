<?php
namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, User};
use App\Notifications\{TargetMovSubmittedNotification, TargetCompletedNotification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DirectorTargetNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_and_completion_notify_assigning_director_once_without_notifying_other_directors(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        Storage::fake('local');
        $office = Office::factory()->create();
        $director = User::factory()->create(['office_id' => $office->id]);
        $director->assignRole(['Campus Director', 'Supervisor']);
        $other = User::factory()->create();
        $other->assignRole('Campus Director');
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('HR Head');
        $target = OfficeTarget::create(['title' => 'Records review', 'success_indicator' => 'One report',
            'target_value' => 1, 'unit_of_measure' => 'report', 'weight' => 20,
            'deadline' => '2026-12-31', 'priority' => 'high', 'created_by' => $director->id]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 1]);
        $target->assignedEmployees()->attach($head->id, ['assigned_by' => $head->id, 'accepted_at' => now(), 'allocated_target_value' => 1]);
        $this->actingAs($head)->post(route('target-movs.store', $target), [
            'accomplishment' => 'Records reviewed', 'completed_value' => 1,
            'files' => [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $notice = $director->notifications()->where('type', TargetMovSubmittedNotification::class)->sole();
        $this->assertEquals($target->id, $notice->data['target_id']);
        $this->assertSame(0, $other->notifications()->count());
        $this->assertSame(1, $head->notifications()->where('type', TargetMovSubmittedNotification::class)->count());
        $this->actingAs($director)->get($notice->data['url'])->assertOk()->assertSee('Records reviewed');
        $this->actingAs($head)->patch(route('target-movs.complete', $target))->assertRedirect();
        $this->patch(route('target-movs.complete', $target))->assertRedirect();
        $this->assertSame(1, $director->notifications()->where('type', TargetCompletedNotification::class)->count());
        $this->assertSame(0, $other->notifications()->count());
    }
}
