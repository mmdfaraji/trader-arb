<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Signal;
use App\Models\PairExchange;
use App\Services\Preflight;
use App\Arbitrage\ArbitrageEngine;
use App\Arbitrage\Adapters\MockBinanceAdapter;
use App\Arbitrage\Adapters\MockCoinbaseAdapter;

class ProcessSignals extends Command
{
    protected $signature = 'signals:process';

    protected $description = 'Process pending arbitrage signals';

    public function handle(): int
    {
        $signals = Signal::with('legs')->where('status', 'PENDING')->get();

        foreach ($signals as $signal) {
            $buyLeg = $signal->legs->firstWhere('side', 'buy');
            $sellLeg = $signal->legs->firstWhere('side', 'sell');
            if (!$buyLeg || !$sellLeg) {
                $signal->update(['status' => 'REJECTED']);
                continue;
            }

            $buyPx = PairExchange::whereHas('exchange', fn($q) => $q->where('name', $buyLeg->exchange))
                ->whereHas('pair', fn($q) => $q->where('symbol', $buyLeg->market))
                ->first();
            $sellPx = PairExchange::whereHas('exchange', fn($q) => $q->where('name', $sellLeg->exchange))
                ->whereHas('pair', fn($q) => $q->where('symbol', $sellLeg->market))
                ->first();
            if (!$buyPx || !$sellPx) {
                $signal->update(['status' => 'REJECTED']);
                continue;
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
                $this->info("Signal {$signal->id} rejected");
                continue;
            }

            $engine = new ArbitrageEngine(new MockBinanceAdapter(), new MockCoinbaseAdapter());
            $payload = [
                'constraints' => $signal->constraints ?? [],
                'legs' => $signal->legs->map(fn($leg) => [
                    'symbol' => $leg->market,
                    'side' => $leg->side,
                    'price' => (float) $leg->price,
                    'qty' => (float) $leg->qty,
                    'tif' => $leg->time_in_force,
                ])->toArray(),
            ];
            $report = $engine->run($payload);
            $signal->update(['status' => $report['status'], 'expected_pnl' => $report['pnl']]);
            $this->info("Signal {$signal->id} {$report['status']}");
        }

        return self::SUCCESS;
    }
}
