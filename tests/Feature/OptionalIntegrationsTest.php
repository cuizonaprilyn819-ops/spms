<?php
namespace Tests\Feature;

use App\Services\AiAssistant;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OptionalIntegrationsTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_email_is_queued_after_database_notification_and_failure_keeps_it(): void
    {
        config(['spms.email_notifications' => true]);
        \Illuminate\Support\Facades\Queue::fake();
        $user = \App\Models\User::factory()->create();
        $user->notify(new class extends \Illuminate\Notifications\Notification {
            public function via($notifiable) { return ['database']; }
            public function toArray($notifiable) { return ['title' => 'Test', 'message' => 'Test message']; }
        });
        $notice = $user->notifications()->sole();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\EmailStoredNotification::class, fn ($job) => $job->notificationId === $notice->id && $job->connection === 'database');
        \Illuminate\Support\Facades\Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        try { (new \App\Jobs\EmailStoredNotification($notice->id))->handle(); }
        catch (\RuntimeException $exception) { $this->assertSame('SMTP unavailable', $exception->getMessage()); }
        $this->assertDatabaseHas('notifications', ['id' => $notice->id]);
    }
    public function test_ai_is_disabled_until_configured(): void
    {
        Http::fake();
        config(['spms.ai.enabled' => false]);
        try { app(AiAssistant::class)->draft('summary', ['details' => 'One session']); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('manual entry', $exception->getMessage()); }
        Http::assertNothingSent();
    }

    public function test_ai_returns_draft_without_storing_at_provider(): void
    {
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test-only', 'spms.ai.model' => 'configured-model']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Draft summary']]],
        ]])]);
        $this->assertSame('Draft summary', app(AiAssistant::class)->draft('summary', ['details' => 'One session']));
        Http::assertSent(fn ($request) => $request['store'] === false && $request['model'] === 'configured-model');
    }

    public function test_ai_failure_preserves_manual_workflow(): void
    {
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test-only', 'spms.ai.model' => 'configured-model']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'private-provider-error'], 500)]);
        $this->expectExceptionMessage('AI assistance is temporarily unavailable. Continue with manual entry.');
        app(AiAssistant::class)->draft('recommendations', ['completion' => 50]);
    }
}
