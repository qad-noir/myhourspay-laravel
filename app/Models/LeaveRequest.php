<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'leave_type_id', 'starts_on', 'ends_on', 'minutes_per_day', 'status', 'reason', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'minutes_per_day' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
