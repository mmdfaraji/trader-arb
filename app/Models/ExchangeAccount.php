<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeAccount extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'exchange_id',
        'label',
        'api_key_ref',
        'is_primary',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(Balance::class);
    }

    public function balanceLocks(): HasMany
    {
        return $this->hasMany(BalanceLock::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

