<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    protected $fillable = ['plan_id', 'interval', 'kind', 'currency', 'amount', 'stripe_price_id', 'tax_inclusive', 'active'];

    protected function casts(): array
    {
        return ['plan_id' => 'integer', 'amount' => 'integer', 'tax_inclusive' => 'boolean', 'active' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
