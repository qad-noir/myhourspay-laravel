<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $fillable = ['calendar_connection_id', 'workspace_id', 'user_id', 'external_id', 'summary', 'starts_at', 'ends_at', 'status', 'hours_entry_id', 'metadata'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'metadata' => 'array'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'calendar_connection_id');
    }

    public function hoursEntry(): BelongsTo
    {
        return $this->belongsTo(HoursEntry::class);
    }
}
