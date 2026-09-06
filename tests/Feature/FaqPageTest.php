<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_faq_page_uses_the_reusable_accordion_layout(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Answers for clearer working hours.')
            ->assertSee('data-faq-group', false)
            ->assertSee('How do approved timesheets work?')
            ->assertSee('Still need a hand?')
            ->assertSee(route('pricing'))
            ->assertSee(route('legal.policy'));
    }

    public function test_pricing_page_reuses_the_faq_component(): void
    {
        $this->get(route('pricing'))
            ->assertOk()
            ->assertSee('Before you choose')
            ->assertSee('data-faq-group', false)
            ->assertSee('Clear plans. No guesswork.');
    }
}
