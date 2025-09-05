<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalLeg extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'signal_id',
        'exchange',
        'market',
        'side',
        'price',
        'qty',
        'time_in_force',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'qty' => 'decimal:8',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    // Relations to Exchange and Pair removed for simplified schema
}
