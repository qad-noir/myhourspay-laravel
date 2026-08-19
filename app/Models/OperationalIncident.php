<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalIncident extends Model
{
    protected $fillable = ['reference', 'event_type', 'severity', 'submitted_name', 'submitted_email', 'ip_address', 'user_agent', 'exception_class', 'exception_message', 'occurred_at', 'resolved_at', 'resolved_by', 'resolution_notes'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }
}
