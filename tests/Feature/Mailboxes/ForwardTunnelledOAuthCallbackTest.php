<?php

namespace Tests\Feature\Mailboxes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForwardTunnelledOAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const string TUNNEL = 'tunnel.example';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://howyknows.test',
            'services.ngrok.domain' => self::TUNNEL,
        ]);
    }

    public function test_the_gmail_callback_over_the_tunnel_is_forwarded_to_the_app_url(): void
    {
        $path = route('mailboxes.gmail.callback', absolute: false);

        $this->get('https://'.self::TUNNEL.$path.'?code=4%2F0Ab&scope=email&state=xyz')
            ->assertRedirect('https://howyknows.test'.$path.'?code=4%2F0Ab&scope=email&state=xyz');
    }

    public function test_a_denied_consent_is_forwarded_too_so_the_app_can_report_it(): void
    {
        $path = route('mailboxes.gmail.callback', absolute: false);

        $this->get('https://'.self::TUNNEL.$path.'?error=access_denied&state=xyz')
            ->assertRedirect('https://howyknows.test'.$path.'?error=access_denied&state=xyz');
    }

    public function test_other_requests_over_the_tunnel_are_left_alone(): void
    {
        $this->get('https://'.self::TUNNEL.'/up')->assertOk();

        $this->get('https://'.self::TUNNEL.route('settings', absolute: false))
            ->assertRedirectContains(self::TUNNEL.'/login');
    }

    public function test_the_callback_on_the_app_url_itself_is_not_forwarded(): void
    {
        $this->get(route('mailboxes.gmail.callback', ['code' => 'abc']))
            ->assertRedirectContains('/login');
    }

    public function test_nothing_is_forwarded_without_a_tunnel_configured(): void
    {
        config(['services.ngrok.domain' => null]);

        $this->get('https://'.self::TUNNEL.route('mailboxes.gmail.callback', absolute: false).'?code=abc')
            ->assertRedirectContains(self::TUNNEL.'/login');
    }
}
