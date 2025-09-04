<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'signal_id',
        'exchange_id',
        'exchange_account_id',
        'pair_id',
        'side',
        'type',
        'tif',
        'client_order_id',
        'exchange_order_id',
        'price',
        'qty',
        'qty_exec',
        'notional',
        'status',
        'filled_qty',
        'avg_price',
        'created_at',
        'sent_at',
        'closed_at',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'qty' => 'decimal:8',
        'qty_exec' => 'decimal:8',
        'notional' => 'decimal:8',
        'filled_qty' => 'decimal:8',
        'avg_price' => 'decimal:8',
        'created_at' => 'datetime',
        'sent_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function exchangeAccount(): BelongsTo
    {
        return $this->belongsTo(ExchangeAccount::class);
    }

    public function pair(): BelongsTo
    {
        return $this->belongsTo(Pair::class);
    }

    public function fills(): HasMany
    {
        return $this->hasMany(OrderFill::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }
}

