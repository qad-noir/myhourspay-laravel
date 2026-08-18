<?php

namespace Tests\Feature;

use Tests\TestCase;

class HttpErrorPageTest extends TestCase
{
    public function test_method_not_allowed_errors_use_the_branded_recovery_page(): void
    {
        $this->post('/')->assertStatus(405)
            ->assertSee('That action isn’t available')
            ->assertSee('Nothing has been changed.');
    }
}
