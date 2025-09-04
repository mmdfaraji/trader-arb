<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceLock extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'exchange_account_id',
        'currency_id',
        'amount',
        'reason',
        'signal_id',
        'created_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function exchangeAccount(): BelongsTo
    {
        return $this->belongsTo(ExchangeAccount::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }
}

