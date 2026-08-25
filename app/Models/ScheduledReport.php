<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'report_template_id', 'frequency', 'recipients', 'next_run_at', 'last_run_at', 'active'];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'next_run_at' => 'datetime', 'last_run_at' => 'datetime', 'active' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
