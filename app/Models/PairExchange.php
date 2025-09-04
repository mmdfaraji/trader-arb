<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PairExchange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'exchange_id',
        'pair_id',
        'exchange_symbol',
        'tick_size',
        'step_size',
        'min_notional',
        'max_order_size',
        'pack_size',
        'maker_fee_bps',
        'taker_fee_bps',
        'status',
    ];

    protected $casts = [
        'tick_size' => 'decimal:8',
        'step_size' => 'decimal:8',
        'min_notional' => 'decimal:8',
        'max_order_size' => 'decimal:8',
        'pack_size' => 'decimal:8',
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function pair(): BelongsTo
    {
        return $this->belongsTo(Pair::class);
    }
}

