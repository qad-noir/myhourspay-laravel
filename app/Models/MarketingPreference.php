<?php

namespace App\Models;

class MarketingPreference extends MarketingModel
{
    protected $guarded = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['consented' => 'boolean', 'consented_at' => 'datetime', 'dismissed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
