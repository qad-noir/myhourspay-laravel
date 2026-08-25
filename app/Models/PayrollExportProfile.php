<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollExportProfile extends Model
{
    protected $fillable = ['workspace_id', 'name', 'format', 'columns', 'settings'];

    protected function casts(): array
    {
        return ['columns' => 'array', 'settings' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
