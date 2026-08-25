<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EntitlementGrant extends Model
{
    protected $fillable = ['public_id', 'user_id', 'plan_id', 'feature_id', 'value', 'starts_at', 'expires_at', 'reason', 'created_by', 'revoked_at', 'revoked_by', 'revocation_reason'];

    protected static function booted(): void
    {
        static::creating(function (self $grant): void {
            $grant->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['value' => 'json', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
