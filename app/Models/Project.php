<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = ['workspace_id', 'client_id', 'name', 'code', 'hourly_rate_minor', 'currency', 'active'];

    protected function casts(): array
    {
        return ['hourly_rate_minor' => 'integer', 'active' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function hoursEntries(): HasMany
    {
        return $this->hasMany(HoursEntry::class);
    }
}
