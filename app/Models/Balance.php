<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Balance extends Model
{
    const CREATED_AT = null;

    protected $fillable = [
        'exchange_account_id',
        'currency_id',
        'available',
        'reserved',
        'updated_at',
    ];

    protected $casts = [
        'available' => 'decimal:8',
        'reserved' => 'decimal:8',
        'updated_at' => 'datetime',
    ];

    public function exchangeAccount(): BelongsTo
    {
        return $this->belongsTo(ExchangeAccount::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}

