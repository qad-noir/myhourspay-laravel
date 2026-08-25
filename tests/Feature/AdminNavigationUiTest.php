<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_layout_boots_livewire_navigation_and_internal_links_opt_in(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('wire:navigate', false)
            ->assertSee('livewire/livewire.js', false);
    }
}
