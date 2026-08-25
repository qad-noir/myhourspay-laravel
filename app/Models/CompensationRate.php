<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationRate extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'effective_from', 'effective_to', 'hourly_rate_minor', 'overtime_multiplier_bps', 'currency'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'hourly_rate_minor' => 'integer', 'overtime_multiplier_bps' => 'integer'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
