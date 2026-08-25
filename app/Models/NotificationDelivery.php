<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'type', 'reference', 'delivered_at'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }
}
