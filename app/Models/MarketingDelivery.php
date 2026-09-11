<?php

namespace App\Models;

class MarketingDelivery extends MarketingModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'available_at' => 'datetime', 'dispatched_at' => 'datetime', 'lease_until' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
