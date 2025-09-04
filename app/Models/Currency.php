<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'symbol',
        'name',
    ];

    public function exchanges(): HasMany
    {
        return $this->hasMany(CurrencyExchange::class);
    }

    public function basePairs(): HasMany
    {
        return $this->hasMany(Pair::class, 'base_currency_id');
    }

    public function quotePairs(): HasMany
    {
        return $this->hasMany(Pair::class, 'quote_currency_id');
    }
}

