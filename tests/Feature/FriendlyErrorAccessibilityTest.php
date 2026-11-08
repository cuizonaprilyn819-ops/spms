<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FriendlyErrorAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_sign_in_has_plain_feedback_linked_to_the_field(): void
    {
        $user = User::factory()->create();
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'incorrect-test-password'])
            ->assertSessionHasErrors(['email' => 'The email or password is incorrect. Check both and try again.']);
        $this->get('/login')->assertOk()->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="email-error"', false)->assertSee('id="email-error"', false)
            ->assertSee('role="alert"', false)->assertDontSee('incorrect-test-password');
    }

    public function test_server_failures_hide_internal_details_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        foreach (['/api/test-error', '/test-error'] as $url) {
            $response = app(ExceptionHandler::class)->render(Request::create($url), new \RuntimeException('SQLSTATE private-host C:/private-source.php'));
            $this->assertSame(500, $response->getStatusCode());
            $this->assertStringContainsString('contact your administrator', $response->getContent());
            $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
            $this->assertStringNotContainsString('private-host', $response->getContent());
            $this->assertStringNotContainsString('private-source.php', $response->getContent());
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_empty_login_fields_explain_the_required_action(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email' => 'Enter your email address.', 'password' => 'Enter your password.']);
    }
}
