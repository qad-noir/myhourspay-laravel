<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'default_break_type', 'default_break_minutes', 'weekly_target_minutes', 'currency', 'overtime_multiplier_bps'];

    protected function casts(): array
    {
        return ['default_break_minutes' => 'integer', 'weekly_target_minutes' => 'integer', 'overtime_multiplier_bps' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot(['role', 'position'])->withTimestamps();
    }

    public function hoursEntries(): HasMany
    {
        return $this->hasMany(HoursEntry::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function compensationRates(): HasMany
    {
        return $this->hasMany(CompensationRate::class);
    }

    public function expectedSchedules(): HasMany
    {
        return $this->hasMany(ExpectedSchedule::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class);
    }
}
