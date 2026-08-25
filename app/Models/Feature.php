<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = ['key', 'name', 'description', 'category', 'mode', 'value_type', 'locked'];

    protected function casts(): array
    {
        return ['locked' => 'boolean'];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)->withPivot('value')->withTimestamps();
    }
}
