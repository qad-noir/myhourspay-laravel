<?php

namespace App\Models;

class Subscription extends \Laravel\Cashier\Subscription
{
    protected function casts(): array
    {
        return ['current_period_ends_at' => 'datetime', 'pending_change_at' => 'datetime', 'stripe_synced_at' => 'datetime'];
    }
}
