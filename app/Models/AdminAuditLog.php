<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminAuditLog extends Model
{
    protected $fillable = ['admin_user_id', 'action', 'before', 'after', 'ip_address'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
