<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureUsageDaily extends Model
{
    protected $table = 'feature_usage_daily';

    protected $fillable = ['usage_date', 'user_id', 'workspace_id', 'feature_id', 'usage_count'];

    protected function casts(): array
    {
        return ['usage_date' => 'date', 'usage_count' => 'integer'];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
