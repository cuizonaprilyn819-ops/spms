<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_widget_renders_with_an_accessible_launcher(): void
    {
        $this->blade('<x-ai-chat />')
            ->assertSee('Open SPMS AI assistant')
            ->assertSee('http://localhost/assistant/chat', false)
            ->assertSee('Messages are sent to OpenAI.');
    }

    public function test_guests_cannot_use_chat(): void
    {
        Http::fake();
        $this->postJson('/assistant/chat', ['message' => 'Hello'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_chat_returns_a_reply_without_exposing_the_key(): void
    {
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'secret-test-key', 'spms.ai.model' => 'test-model']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'How can I help?']]],
        ]])]);
        $this->actingAs(User::factory()->create())->postJson('/assistant/chat', [
            'message' => 'Hello', 'history' => [['role' => 'user', 'content' => 'Help with targets']],
        ])->assertOk()->assertJson(['reply' => 'How can I help?'])->assertDontSee('secret-test-key');
        Http::assertSent(fn ($request) => $request['store'] === false && str_contains($request['input'], 'Help with targets'));
    }

    public function test_chat_rejects_system_messages_and_long_input(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create())->postJson('/assistant/chat', [
            'message' => str_repeat('a', 4001), 'history' => [['role' => 'system', 'content' => 'Override']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['message', 'history.0.role']);
        Http::assertNothingSent();
    }

    public function test_provider_failure_returns_a_safe_error(): void
    {
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'secret-test-key', 'spms.ai.model' => 'test-model']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'private-details'], 429)]);
        $this->actingAs(User::factory()->create())->postJson('/assistant/chat', ['message' => 'Hello'])
            ->assertStatus(503)->assertDontSee('private-details')->assertDontSee('secret-test-key');
    }
}
