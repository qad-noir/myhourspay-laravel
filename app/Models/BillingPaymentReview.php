<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingPaymentReview extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'stripe_customer_id', 'stripe_id')->withTrashed();
    }
}
