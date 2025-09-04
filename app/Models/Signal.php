<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Signal extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'created_at',
        'ttl_ms',
        'status',
        'source',
        'constraints',
        'expected_pnl',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'constraints' => 'array',
        'expected_pnl' => 'decimal:8',
    ];

    public function legs(): HasMany
    {
        return $this->hasMany(SignalLeg::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SignalEvent::class);
    }
}
