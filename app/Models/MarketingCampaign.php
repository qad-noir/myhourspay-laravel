<?php

namespace App\Models;

class MarketingCampaign extends MarketingModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'day' => 'integer'];
    }
}
