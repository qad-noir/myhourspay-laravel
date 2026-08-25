<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['key', 'name', 'description', 'tier', 'active', 'purchasable'];

    protected function casts(): array
    {
        return ['tier' => 'integer', 'active' => 'boolean', 'purchasable' => 'boolean'];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class)->withPivot('value')->withTimestamps();
    }
}
