<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PublicFrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_public_navigation_shows_dashboard_instead_of_login(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('href="'.route('login').'"', false)
            ->assertDontSee('href="'.route('register').'"', false);
    }

    public function test_landing_page_uses_the_public_brand_and_real_auth_routes(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('myhourspay')
            ->assertSee('Track your hours.')
            ->assertSee('Everything you need to')
            ->assertSee('Acme Studio')
            ->assertSee('Hours calendar')
            ->assertSee('Private workspace')
            ->assertSee('class="session-project"', false)
            ->assertSee('Tracking')
            ->assertSee('View report')
            ->assertSee('class="dashboard-icon"', false)
            ->assertSee('property="og:image" content="https://myhourspay.com/og-image.jpg"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('rel="canonical" href="https://myhourspay.com"', false)
            ->assertSee('href="'.route('login').'"', false);

        if (Features::enabled(Features::registration())) {
            $response->assertSee('href="'.route('register').'"', false);
        }
    }

    public function test_open_graph_image_has_standard_dimensions_and_a_small_payload(): void
    {
        $path = public_path(config('site.social.image'));
        $image = getimagesize($path);

        $this->assertSame(1200, $image[0]);
        $this->assertSame(630, $image[1]);
        $this->assertSame('image/jpeg', $image['mime']);
        $this->assertLessThan(300_000, filesize($path));
        $this->assertSame('myhourspay.com', config('site.domain'));
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
