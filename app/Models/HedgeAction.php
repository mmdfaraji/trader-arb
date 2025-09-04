<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HedgeAction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'signal_id',
        'cause',
        'from_order_id',
        'hedge_order_id',
        'qty',
        'status',
        'result_details',
        'created_at',
    ];

    protected $casts = [
        'qty' => 'decimal:8',
        'created_at' => 'datetime',
        'result_details' => 'array',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    public function fromOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'from_order_id');
    }

    public function hedgeOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'hedge_order_id');
    }
}

