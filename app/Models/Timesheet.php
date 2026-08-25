<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'week_start', 'status', 'submission_note', 'review_note', 'submitted_at', 'reviewed_by', 'reviewed_at', 'locked_at'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'locked_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(HoursEntry::class);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['approved', 'locked'], true);
    }
}
