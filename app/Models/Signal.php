<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Signal extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'signal_created_at',
        'ttl_ms',
        'constraints',
        'state',
    ];

    protected $casts = [
        'signal_created_at' => 'datetime',
        'constraints' => 'array',
    ];

    public function legs(): HasMany
    {
        return $this->hasMany(SignalLeg::class);
    }
}
