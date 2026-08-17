<?php

namespace Tests\Feature;

use Laravel\Fortify\Features;
use Tests\TestCase;

class PublicFrontendTest extends TestCase
{
    public function test_landing_page_uses_the_public_brand_and_real_auth_routes(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('myhourspay')
            ->assertSee('Track your hours.')
            ->assertSee('Everything you need to')
            ->assertSee('href="'.route('login').'"', false);

        if (Features::enabled(Features::registration())) {
            $response->assertSee('href="'.route('register').'"', false);
        }
    }

    public function test_login_page_is_connected_to_fortify(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('<title>myhourspay</title>', false)
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_registration_page_contains_server_matched_password_guidance(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $this->get('/register')->assertOk()
            ->assertSee('action="'.route('register').'"', false)
            ->assertSee('At least 8 characters')
            ->assertSee('Contains a number')
            ->assertSee('Contains uppercase &amp; lowercase', false)
            ->assertDontSee('name="terms"', false);
    }
}
