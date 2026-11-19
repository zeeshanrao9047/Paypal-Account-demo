<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reference',
        'paypal_order_id',
        'capture_id',
        'amount',
        'currency',
        'status',
        'payer_email',
        'paypal_response',
    ];

    protected $casts = [
        'paypal_response' => 'array',
    ];
}
