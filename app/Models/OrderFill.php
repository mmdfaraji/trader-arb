<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFill extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'fill_seq',
        'filled_qty',
        'price',
        'fee_amount',
        'fee_currency_id',
        'trade_id',
        'filled_at',
    ];

    protected $casts = [
        'filled_qty' => 'decimal:8',
        'price' => 'decimal:8',
        'fee_amount' => 'decimal:8',
        'filled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function feeCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'fee_currency_id');
    }
}

