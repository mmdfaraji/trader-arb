<?php

namespace App\Jobs;

use App\Arbitrage\Adapters\ExchangeAdapterFactory;
use App\Arbitrage\ArbitrageEngine;
use App\Models\PairExchange;
use App\Models\Signal;
use App\Services\Preflight;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSignalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $signalId)
    {
    }

    public function handle(): void
    {
        $signal = Signal::with('legs')->find($this->signalId);
        if (!$signal || $signal->status !== 'PENDING') {
            return;
        }

        $buyLeg = $signal->legs->firstWhere('side', 'buy');
        $sellLeg = $signal->legs->firstWhere('side', 'sell');
        if (!$buyLeg || !$sellLeg) {
            $signal->update(['status' => 'REJECTED']);
            return;
        }

        $buyPx = PairExchange::whereHas('exchange', fn($q) => $q->where('name', $buyLeg->exchange))
            ->whereHas('pair', fn($q) => $q->where('symbol', $buyLeg->market))
            ->first();
        $sellPx = PairExchange::whereHas('exchange', fn($q) => $q->where('name', $sellLeg->exchange))
            ->whereHas('pair', fn($q) => $q->where('symbol', $sellLeg->market))
            ->first();
        if (!$buyPx || !$sellPx) {
            $signal->update(['status' => 'REJECTED']);
            return;
        }

        $preflight = new Preflight(
            feeBps: ['buy' => $buyPx->taker_fee_bps ?? 0, 'sell' => $sellPx->taker_fee_bps ?? 0],
            slippageBps: ['buy' => $buyPx->slippage_bps ?? 0, 'sell' => $sellPx->slippage_bps ?? 0]
        );

        $execQty = min($buyLeg->qty, $sellLeg->qty);
        $pnl = $preflight->expectedNetPnlWithSlippage([
            'buy' => $buyLeg->price,
            'sell' => $sellLeg->price,
        ], $execQty);
        $min = $signal->constraints['Min_expected_pnl'] ?? 0;
        if (!$preflight->passesMinPnl($pnl, $min)) {
            $signal->update(['status' => 'REJECTED', 'expected_pnl' => $pnl]);
            return;
        }

        $factory = new ExchangeAdapterFactory();
        $adapters = [];
        foreach ($signal->legs->pluck('exchange')->unique() as $exName) {
            try {
                $adapters[$exName] = $factory->make($exName);
            } catch (\InvalidArgumentException $e) {
                $signal->update(['status' => 'REJECTED']);
                return;
            }
        }

        $engine = new ArbitrageEngine($adapters);
        $payload = [
            'id' => $signal->id,
            'constraints' => $signal->constraints ?? [],
            'legs' => $signal->legs->map(fn($leg) => [
                'exchange' => $leg->exchange,
                'symbol' => $leg->market,
                'side' => $leg->side,
                'price' => (float) $leg->price,
                'qty' => (float) $leg->qty,
                'tif' => $leg->time_in_force,
                'exchange' => $leg->exchange,
            ])->toArray(),
        ];
        $report = $engine->run($payload);
        $signal->update(['status' => $report['status'], 'expected_pnl' => $report['pnl']]);
    }
}
