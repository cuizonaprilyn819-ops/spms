<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutRecoveryTest extends TestCase
{
    use RefreshDatabase;
    public function test_expired_logout_can_be_retried_with_fresh_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'current-token'])->post(route('logout'), ['_token' => 'old-token'])
            ->assertRedirect(route('logout.recover'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('logout.recover'))->assertOk()->assertSee('Confirm logout')->assertSee('current-token');
        $this->post(route('logout'), ['_token' => 'current-token'])->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_expired_session_recovery_redirects_to_login(): void
    {
        $this->get(route('logout.recover'))->assertRedirect(route('login'));
    }
}
