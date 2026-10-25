<?php

namespace Tests\Feature;

use Tests\TestCase;

class PageExpiredTest extends TestCase
{
    public function test_stale_login_returns_to_a_fresh_form_without_preserving_credentials(): void
    {
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'current-token'])->post('/login', [
            '_token' => 'stale-token',
            'email' => 'person@example.com',
            'password' => 'private-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status')
            ->assertSessionMissing('_old_input');

        $this->assertGuest();
        $response = $this->get('/login')->assertOk()
            ->assertSee('Your sign-in page expired')
            ->assertSee('current-token')
            ->assertDontSee('private-password');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_json_login_keeps_the_419_response(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/login')->assertStatus(419)->assertJsonStructure(['message']);
    }

    public function test_other_expired_forms_show_recovery_instructions(): void
    {
        $this->app['env'] = 'local';
        $this->post('/forgot-password')->assertStatus(419)
            ->assertSee('This submission was not processed.')
            ->assertSee('Return to sign in');
    }
}
