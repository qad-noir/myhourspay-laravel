<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        app(BillingSettings::class)->set('paid_enforcement_enabled', false);
        PlanPrice::query()->where('kind', 'base')->get()->each(function (PlanPrice $price): void {
            $price->update(['stripe_price_id' => 'price_'.$price->plan->key.'_'.$price->interval]);
        });
    }

    public function test_public_pricing_page_shows_catalogue_prices_features_and_links(): void
    {
        $response = $this->get(route('pricing'));

        $response->assertOk()
            ->assertSee('Start with your hours.')
            ->assertSee('Free')
            ->assertSee('Pro')
            ->assertSee('Business')
            ->assertSee('Monthly')
            ->assertSee('Yearly')
            ->assertSee(route('legal.policy'))
            ->assertSee(route('legal.terms'))
            ->assertSee(route('pricing'))
            ->assertSee('Time tracking')
            ->assertSee('What’s included?');
    }

    public function test_pricing_page_switches_interval_and_beta_copy_without_exposing_admin_settings(): void
    {
        $this->assertFalse(app(BillingSettings::class)->boolean('paid_enforcement_enabled'));
        $response = $this->get(route('pricing', ['interval' => 'yearly']));

        $response->assertOk()->assertSee('year')->assertSee('Beta access')->assertSee('Premium features are currently available while we test myhourspay.')
            ->assertSee('data-pricing-page', false)
            ->assertSee('data-pricing-switch="monthly"', false)
            ->assertSee('data-pricing-switch="yearly"', false)
            ->assertSee('data-pricing-panel="monthly"', false)
            ->assertSee('data-pricing-panel="yearly"', false)
            ->assertDontSee('paid_enforcement_enabled')
            ->assertDontSee('Paid enforcement');
    }

    public function test_interval_controls_are_client_side_without_interval_navigation_links(): void
    {
        $response = $this->get(route('pricing'));

        $response->assertOk()
            ->assertSee('data-pricing-interval', false)
            ->assertDontSee('/pricing?interval=monthly')
            ->assertDontSee('/pricing?interval=yearly');
    }

    public function test_public_header_points_to_pricing_and_privacy(): void
    {
        foreach ([route('pricing'), route('legal.policy'), route('legal.terms')] as $url) {
            $this->get($url)->assertOk()->assertSee(route('pricing'))->assertSee(route('legal.policy'))->assertSee(route('legal.terms'));
        }
    }

    public function test_pricing_page_uses_current_catalogue_and_does_not_require_login(): void
    {
        $pro = Plan::where('key', 'pro')->firstOrFail();
        PlanPrice::where('plan_id', $pro->id)->where('kind', 'base')->where('interval', 'monthly')->update(['amount' => 777]);
        $this->get(route('pricing'))->assertOk()->assertSee('£7.77');
        $this->actingAs(User::factory()->create())->get(route('pricing'))->assertOk()->assertSee('Manage plans');
    }
}
