<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionReport extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'signal_id',
        'final_state',
        'net_position_delta',
        'pnl_realized',
        'latency_ms',
        'slippage_bps',
        'created_at',
    ];

    protected $casts = [
        'net_position_delta' => 'decimal:8',
        'pnl_realized' => 'decimal:8',
        'slippage_bps' => 'decimal:8',
        'created_at' => 'datetime',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }
}

