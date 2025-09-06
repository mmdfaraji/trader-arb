<?php

namespace App\Jobs;

use App\Arbitrage\Adapters\ExchangeAdapterFactory;
use App\Arbitrage\ArbitrageEngine;
use App\Models\ExchangeAccount;
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

    public function __construct(public string $signalId) {}

    public function handle(): void
    {
        $signal = Signal::with('legs')->find($this->signalId);
        if (! $signal || $signal->status !== 'PENDING') {
            return;
        }

        $buyLeg = $signal->legs->firstWhere('side', 'buy');
        $sellLeg = $signal->legs->firstWhere('side', 'sell');
        if (! $buyLeg || ! $sellLeg) {
            $signal->update(['status' => 'REJECTED']);

            return;
        }

        $buyPx = PairExchange::whereHas('exchange', fn ($q) => $q->where('name', $buyLeg->exchange))
            ->whereHas('pair', fn ($q) => $q->where('symbol', $buyLeg->market))
            ->first();
        $sellPx = PairExchange::whereHas('exchange', fn ($q) => $q->where('name', $sellLeg->exchange))
            ->whereHas('pair', fn ($q) => $q->where('symbol', $sellLeg->market))
            ->first();
        if (! $buyPx || ! $sellPx) {
            $signal->update(['status' => 'REJECTED']);

            return;
        }

        $preflight = new Preflight(
            feeBps: ['buy' => $buyPx->taker_fee_bps ?? 0, 'sell' => $sellPx->taker_fee_bps ?? 0],
            slippageBps: ['buy' => $buyPx->slippage_bps ?? 0, 'sell' => $sellPx->slippage_bps ?? 0]
        );

        $buyAccount = ExchangeAccount::where('exchange_id', $buyPx->exchange_id)->first();
        $sellAccount = ExchangeAccount::where('exchange_id', $sellPx->exchange_id)->first();

        $buyBal = $buyAccount?->balances()->where('currency_id', $buyPx->pair->quote_currency_id)->first();
        $sellBal = $sellAccount?->balances()->where('currency_id', $sellPx->pair->base_currency_id)->first();

        $balances = [
            'buy' => (float) ($buyBal->available ?? 0),
            'sell' => (float) ($sellBal->available ?? 0),
        ];
        $reserved = [
            'buy' => (float) ($buyBal->reserved ?? 0),
            'sell' => (float) ($sellBal->reserved ?? 0),
        ];
        $legDefs = [
            'buy' => [
                'side' => 'buy',
                'price' => (float) $buyLeg->price,
                'qty' => (float) $buyLeg->qty,
                'min_notional' => (float) ($buyPx->min_notional ?? 0),
                'max_qty' => (float) ($buyPx->max_order_size ?? PHP_FLOAT_MAX),
            ],
            'sell' => [
                'side' => 'sell',
                'price' => (float) $sellLeg->price,
                'qty' => (float) $sellLeg->qty,
                'min_notional' => (float) ($sellPx->min_notional ?? 0),
                'max_qty' => (float) ($sellPx->max_order_size ?? PHP_FLOAT_MAX),
            ],
        ];

        $execQty = $preflight->computeExecutableQty($balances, $reserved, $legDefs);
        $requestedQty = min((float) $buyLeg->qty, (float) $sellLeg->qty);
        if ($execQty <= 0 || $execQty < $requestedQty) {
            $signal->update(['status' => 'REJECTED']);

            return;
        }

        $pnl = $preflight->expectedNetPnlWithSlippage([
            'buy' => $buyLeg->price,
            'sell' => $sellLeg->price,
        ], $execQty);
        $min = $signal->constraints['Min_expected_pnl'] ?? 0;
        if (! $preflight->passesMinPnl($pnl, $min)) {
            $signal->update(['status' => 'REJECTED', 'expected_pnl' => $pnl]);

            return;
        }

        $factory = new ExchangeAdapterFactory;
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
            'constraints' => $signal->constraints ?? [],
            'legs' => $signal->legs->map(fn ($leg) => [
                'symbol' => $leg->market,
                'side' => $leg->side,
                'price' => (float) $leg->price,
                'qty' => $execQty,
                'tif' => $leg->time_in_force,
                'exchange' => $leg->exchange,
            ])->toArray(),
        ];
        $engineBalances = [
            max(0, $balances['buy'] - $reserved['buy']) / (float) $buyLeg->price,
            max(0, $balances['sell'] - $reserved['sell']),
        ];
        $report = $engine->run($payload, ['balances' => $engineBalances]);
        $signal->update(['status' => $report['status'], 'expected_pnl' => $report['pnl']]);
    }
}
