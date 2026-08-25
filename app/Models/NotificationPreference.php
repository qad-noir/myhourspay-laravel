<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'type', 'enabled', 'channels', 'settings'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'channels' => 'array', 'settings' => 'array'];
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
