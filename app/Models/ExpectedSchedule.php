<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpectedSchedule extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'project_id', 'day_of_week', 'start_time', 'end_time', 'break_minutes', 'break_type', 'active', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'break_minutes' => 'integer', 'active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
