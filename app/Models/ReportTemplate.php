<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = ['workspace_id', 'user_id', 'name', 'filters', 'columns', 'format'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'columns' => 'array'];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }
}
