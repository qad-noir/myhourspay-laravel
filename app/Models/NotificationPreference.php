<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'type', 'enabled', 'channels', 'settings'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'channels' => 'array', 'settings' => 'array'];
    }
}
