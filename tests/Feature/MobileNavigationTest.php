<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class MobileNavigationTest extends TestCase
{
    public function test_route_groups_select_the_correct_shortcut(): void
    {
        foreach (['dashboard' => 'Overview', 'hours.entries.edit' => 'Hours', 'hours.reports.print' => 'Reports', 'pro.schedules' => null, 'billing.index' => null] as $name => $label) {
            $request = Request::create('/irrelevant?month=2026-09');
            $route = (new Route('GET', '/irrelevant', fn () => null))->name($name);
            $request->setRouteResolver(fn () => $route);
            $this->app->instance('request', $request);
            $html = Blade::render('<x-dashboard.mobile-navigation />');
            $this->assertSame($label ? 1 : 0, substr_count($html, 'aria-current="page"'));
            $this->assertStringContainsString('aria-controls="dashboard-sidebar"', $html);
            $this->assertStringNotContainsString('href="/more"', $html);
            if ($label) {
                $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>.*?mobile-navigation__label">'.$label.'<\/span>/s', $html);
            }
        }
    }
}
