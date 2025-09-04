<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pair extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'base_currency_id',
        'quote_currency_id',
        'symbol',
    ];

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_id');
    }

    public function exchanges(): HasMany
    {
        return $this->hasMany(PairExchange::class);
    }
}

