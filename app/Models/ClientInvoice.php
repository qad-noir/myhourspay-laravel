<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = ['workspace_id', 'client_id', 'number', 'status', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor', 'issued_on', 'due_on', 'sent_at', 'paid_at', 'voided_at', 'snapshot'];

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'issued_on' => 'date', 'due_on' => 'date', 'sent_at' => 'datetime', 'paid_at' => 'datetime', 'voided_at' => 'datetime', 'snapshot' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ClientInvoiceLine::class);
    }
}
