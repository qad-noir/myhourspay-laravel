<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInvoiceLine extends Model
{
    protected $fillable = ['client_invoice_id', 'hours_entry_id', 'description', 'quantity_minutes', 'rate_minor', 'amount_minor', 'snapshot'];

    protected function casts(): array
    {
        return ['quantity_minutes' => 'integer', 'rate_minor' => 'integer', 'amount_minor' => 'integer', 'snapshot' => 'array'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    public function hoursEntry(): BelongsTo
    {
        return $this->belongsTo(HoursEntry::class);
    }
}
