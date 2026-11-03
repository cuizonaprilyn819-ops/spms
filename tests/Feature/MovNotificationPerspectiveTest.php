<?php

namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, TargetMov, User};
use App\Notifications\TargetMovSubmittedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovNotificationPerspectiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitter_gets_you_wording_and_parent_head_gets_named_wording_once_per_batch(): void
    {
        $this->seed(RoleSeeder::class);
        config(['spms.ai.enabled' => false]);
        Storage::fake('local');
        $parent = Office::factory()->create(['campus' => 6]);
        $office = Office::factory()->create(['campus' => 6, 'parent_office_id' => $parent->id]);
        $submitter = User::factory()->create(['name' => 'Lawly Mandigma', 'office_id' => $office->id])->assignRole('Supervisor');
        $director = User::factory()->create(['office_id' => $parent->id])->assignRole('Campus Director');
        $unrelated = User::factory()->create(['office_id' => Office::factory()->create(['campus' => 6])->id])->assignRole('Supervisor');
        $target = OfficeTarget::create(['title' => 'University articles', 'success_indicator' => 'Two articles', 'target_value' => 2, 'unit_of_measure' => 'articles', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $submitter->id]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 2]);
        $target->assignedEmployees()->attach($submitter, ['accepted_at' => now(), 'allocated_target_value' => 2, 'assigned_by' => $submitter->id]);
        $this->actingAs($submitter)->post(route('movs.store'), ['target_ref' => 'office:'.$target->id, 'title' => 'Article evidence', 'files' => [UploadedFile::fake()->create('article1.pdf', 1, 'application/pdf'), UploadedFile::fake()->create('article2.pdf', 1, 'application/pdf')]])->assertSessionHasNoErrors()->assertRedirect();
        $own = $submitter->notifications()->sole()->data;
        $parentNotice = $director->notifications()->sole()->data;
        $this->assertSame('You submitted your evidence for "University articles" to your supervisor.', $own['message']);
        $this->assertSame('Lawly Mandigma submitted evidence for "University articles".', $parentNotice['message']);
        $this->assertCount(2, $own['mov_ids']);
        $this->assertSame(0, $unrelated->notifications()->count());
        $this->get(route('notifications.index'))->assertOk()->assertSee('You submitted your evidence');
        $this->actingAs($director)->get(route('notifications.index'))->assertOk()->assertSee('Lawly Mandigma submitted evidence');
    }

    public function test_old_self_notifications_are_reworded_on_display_without_rewriting_the_saved_record(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create()->assignRole('Supervisor');
        $office = Office::factory()->create();
        $target = OfficeTarget::create(['title' => 'Maintenance', 'success_indicator' => 'One maintenance', 'target_value' => 1, 'unit_of_measure' => 'maintenance', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $user->id]);
        $mov = TargetMov::create(['office_target_id' => $target->id, 'office_id' => $office->id, 'user_id' => $user->id, 'path' => 'proof.pdf', 'original_name' => 'proof.pdf', 'accomplishment' => 'Completed']);
        $notice = $user->notifications()->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => TargetMovSubmittedNotification::class, 'data' => ['title' => 'MOV submitted for review', 'mov_id' => $mov->id, 'message' => $user->name.' submitted evidence.', 'url' => '/office-targets/'.$target->id]]);
        $before = $notice->data;
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('You submitted your evidence')->assertDontSee($user->name.' submitted evidence.');
        $this->assertSame($before, $notice->fresh()->data);
        $this->assertNull($notice->fresh()->read_at);
    }
}
