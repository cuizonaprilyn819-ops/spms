<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Ipcr;
use App\Models\PerformanceTarget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_notification_links_use_the_current_app_address(): void
    {
        $user = User::factory()->create();
        $notice = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => \App\Notifications\TargetAssignedNotification::class,
            'data' => ['title' => 'Assigned target', 'url' => 'http://localhost/office-targets/3'],
        ]);

        $this->actingAs($user)->withServerVariables(['HTTP_HOST' => '127.0.0.1:8000', 'SERVER_PORT' => 8000])
            ->patch('http://127.0.0.1:8000/notifications/'.$notice->id.'/read')
            ->assertRedirect('http://127.0.0.1:8000/office-targets/3');
        $this->assertNotNull($notice->fresh()->read_at);
        $this->get('http://127.0.0.1:8000/notifications')
            ->assertOk()->assertSee('href="/office-targets/3"', false)
            ->assertDontSee('href="http://localhost/office-targets/3"', false);
    }

    public function test_deadline_reminder_notifications_are_sent_for_targets_due_soon(): void
    {
        $this->seed(RoleSeeder::class);

        $creator = User::factory()->create();
        $target = PerformanceTarget::factoryLike()->create([
            'created_by_user' => $creator,
            'deadline' => now()->addDays(3)->toDateString(),
        ]);

        $this->artisan('spms:send-deadline-reminders')
            ->expectsOutput('1 deadline reminder notification(s) sent.')
            ->assertSuccessful();

        $notification = $creator->notifications()->firstOrFail();

        $this->assertSame('deadline_reminder', $notification->data['kind']);
        $this->assertSame($target->id, $notification->data['target_id']);
        $this->assertStringContainsString($target->target, $notification->data['message']);
    }

    public function test_deadline_reminders_are_not_duplicated(): void
    {
        $this->seed(RoleSeeder::class);

        $creator = User::factory()->create();
        PerformanceTarget::factoryLike()->create([
            'created_by_user' => $creator,
            'deadline' => now()->addDays(2)->toDateString(),
        ]);

        $this->artisan('spms:send-deadline-reminders')->assertSuccessful();
        $this->artisan('spms:send-deadline-reminders')
            ->expectsOutput('0 deadline reminder notification(s) sent.')
            ->assertSuccessful();

        $this->assertSame(1, $creator->notifications()->count());
    }

    public function test_approval_notifications_are_sent_to_form_preparers(): void
    {
        $this->seed(RoleSeeder::class);

        $approver = User::factory()->create();
        $approver->assignRole('Supervisor');
        $preparedBy = User::factory()->create();
        $ipcr = Ipcr::factoryLike()->create([
            'prepared_by_user' => $preparedBy,
        ]);

        $this
            ->actingAs($approver)
            ->patch(route('ipcrs.approve', $ipcr))
            ->assertRedirect(route('ipcrs.show', $ipcr));

        $notification = $preparedBy->notifications()->firstOrFail();

        $this->assertSame('approval_notification', $notification->data['kind']);
        $this->assertSame('IPCR', $notification->data['form_type']);
        $this->assertStringContainsString('approved', $notification->data['message']);
    }

    public function test_evaluation_notifications_are_sent_to_employees(): void
    {
        $this->seed(RoleSeeder::class);

        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create();

        $this
            ->actingAs($supervisor)
            ->post(route('evaluations.store'), [
                'employee_id' => $employee->id,
                'performance_target_id' => $target->id,
                'quality_rating' => 4,
                'efficiency_rating' => 4.25,
                'timeliness_rating' => 4.5,
                'remarks' => 'Good performance.',
            ])
            ->assertRedirect();

        $evaluation = Evaluation::firstOrFail();
        $notification = $employee->notifications()->firstOrFail();

        $this->assertSame('evaluation_notification', $notification->data['kind']);
        $this->assertSame($evaluation->id, $notification->data['evaluation_id']);
        $this->assertStringContainsString('4.25', $notification->data['message']);
        $this->assertStringContainsString('Very Satisfactory', $notification->data['message']);
    }

    public function test_users_can_view_and_mark_notifications_read(): void
    {
        $this->seed(RoleSeeder::class);

        $creator = User::factory()->create();
        PerformanceTarget::factoryLike()->create([
            'created_by_user' => $creator,
            'deadline' => now()->addDay()->toDateString(),
        ]);

        $this->artisan('spms:send-deadline-reminders')->assertSuccessful();

        $notification = $creator->notifications()->firstOrFail();

        $this
            ->actingAs($creator)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Deadline Reminder');

        $this
            ->actingAs($creator)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
