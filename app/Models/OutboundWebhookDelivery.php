<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundWebhookDelivery extends Model
{
    protected $fillable = ['public_id', 'outbound_webhook_endpoint_id', 'event_type', 'payload', 'attempts', 'status', 'response_status', 'response_excerpt', 'next_attempt_at', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'next_attempt_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(OutboundWebhookEndpoint::class, 'outbound_webhook_endpoint_id');
    }
}
