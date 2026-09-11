<?php

namespace App\Models;

class MarketingEnrollment extends MarketingModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime'];
    }
}
