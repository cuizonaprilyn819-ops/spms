<?php

namespace Tests\Feature;

use App\Models\{Ipcr, Mov, PerformanceTarget, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivateWorkflowCacheTest extends TestCase
{
    use RefreshDatabase;

    private function privateResponse($response): void
    {
        $header = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $header);
        $this->assertStringContainsString('private', $header);
        $this->assertStringNotContainsString('public', $header);
    }

    public function test_html_api_and_permission_failures_are_not_cached_and_role_changes_take_effect(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin);
        $this->privateResponse($this->get(route('employees.index'))->assertOk());
        $this->privateResponse($this->get('/api/notifications')->assertOk());
        $admin->syncRoles(['Employee']);
        $this->actingAs($admin->fresh());
        $this->privateResponse($this->get(route('employees.index'))->assertForbidden());
        $this->privateResponse($this->getJson('/api/employees')->assertForbidden());
    }

    public function test_notifications_show_current_database_content_on_each_request(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create()->assignRole('Employee');
        $notification = DatabaseNotification::create(['id' => (string) Str::uuid(), 'type' => 'test-notice',
            'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id, 'data' => ['title' => 'Old notification', 'message' => 'Before update']]);
        $this->actingAs($user);
        $this->privateResponse($this->get(route('notifications.index'))->assertOk()->assertSee('Before update'));
        $notification->update(['data' => ['title' => 'Current notification', 'message' => 'Latest update'], 'read_at' => now()]);
        $this->privateResponse($this->get(route('notifications.index'))->assertOk()->assertSee('Latest update')->assertDontSee('Before update'));
    }

    public function test_authorized_legacy_mov_downloads_are_private_and_other_users_are_denied(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        $user = User::factory()->create()->assignRole('Employee');
        $report = Ipcr::factoryLike()->create(['employee' => $user, 'prepared_by_user' => $user]);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $report]);
        Storage::disk('public')->put('movs/private.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXt0AAAAASUVORK5CYII='));
        $mov = Mov::create(['performance_target_id' => $target->id, 'uploaded_by' => $user->id,
            'title' => 'Private record', 'file_path' => 'movs/private.png', 'original_name' => 'private.png', 'mime_type' => 'image/png', 'extension' => 'png', 'file_size' => 68]);
        $this->actingAs($user);
        $this->privateResponse($this->get(route('movs.download', $mov))->assertDownload('private.png'));
        $this->privateResponse($this->get(route('movs.preview', $mov))->assertOk());
        $this->actingAs(User::factory()->create()->assignRole('Employee'));
        $this->privateResponse($this->get(route('movs.download', $mov))->assertForbidden());
    }
}
