<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exchange extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'api_url',
        'ws_url',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function currencyExchanges(): HasMany
    {
        return $this->hasMany(CurrencyExchange::class);
    }

    public function pairExchanges(): HasMany
    {
        return $this->hasMany(PairExchange::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ExchangeAccount::class);
    }
}

