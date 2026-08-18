<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoursEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_date',
        'start_time',
        'end_time',
        'break_minutes',
        'notes',
        'workspace_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'break_minutes' => 'integer',
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
