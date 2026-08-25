<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingWebhookEvent extends Model
{
    protected $fillable = ['stripe_event_id', 'type', 'status', 'payload_hash', 'processed_at', 'failed_at', 'error_message'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
