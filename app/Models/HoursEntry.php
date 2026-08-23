<?php

namespace App\Models;

use App\Services\HoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'week_start' => 'date:Y-m-d',
            'break_minutes' => 'integer',
            'net_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (HoursEntry $entry): void {
            $entry->net_minutes = app(HoursCalculator::class)->calculateNetMinutes(
                substr((string) $entry->start_time, 0, 5),
                substr((string) $entry->end_time, 0, 5),
                (int) $entry->break_minutes,
                (string) ($entry->break_type ?? 'unpaid'),
            );
            $entry->week_start = CarbonImmutable::parse($entry->work_date, config('hours.timezone'))
                ->startOfWeek()
                ->toDateString();
        });
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
