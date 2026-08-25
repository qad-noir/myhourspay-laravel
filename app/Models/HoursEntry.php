<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class HoursEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'work_date',
        'start_time',
        'end_time',
        'break_minutes',
        'break_type',
        'notes',
        'workspace_id',
        'project_id',
        'timesheet_id',
        'billable',
        'hourly_rate_minor',
        'overtime_multiplier_bps',
        'currency',
        'earnings_minor',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'week_start' => 'date:Y-m-d',
            'break_minutes' => 'integer',
            'net_minutes' => 'integer',
            'billable' => 'boolean',
            'hourly_rate_minor' => 'integer',
            'overtime_multiplier_bps' => 'integer',
            'earnings_minor' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoiceLine(): HasOne
    {
        return $this->hasOne(ClientInvoiceLine::class);
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    public function scopeForPeriod(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('work_date', [$start, $end]);
    }

    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        return $query->where('workspace_id', $workspace instanceof Workspace ? $workspace->getKey() : $workspace);
    }
}
