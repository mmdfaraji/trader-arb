<?php

namespace App\Jobs;

use App\Arbitrage\Adapters\MockBinanceAdapter;
use App\Arbitrage\Adapters\MockCoinbaseAdapter;
use App\Models\Signal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $exchange,
        public string $orderId,
        public string $signalId
    ) {
    }

    public function handle(): void
    {
        $adapter = match ($this->exchange) {
            'binance' => new MockBinanceAdapter(),
            'coinbase' => new MockCoinbaseAdapter(),
            default => throw new \InvalidArgumentException("Unknown exchange {$this->exchange}"),
        };

        $attempt = 0;
        $order = $adapter->getOrder($this->orderId);
        while (in_array($order['status'], ['NEW', 'PARTIALLY_FILLED'], true) && $attempt < 5) {
            usleep($this->calculateBackoff($attempt) * 1000);
            $order = $adapter->getOrder($this->orderId);
            $attempt++;
        }
        if ($order['status'] === 'NEW') {
            $adapter->cancelOrder($this->orderId);
            $order['status'] = 'CANCELED';
        }

        $signal = Signal::with(['orders', 'legs'])->find($this->signalId);
        if (!$signal) {
            return;
        }

        $orderModel = $signal->orders->firstWhere('exchange_order_id', $this->orderId);
        if ($orderModel) {
            $orderModel->update([
                'status' => $order['status'],
                'qty_exec' => $order['executed_qty'] ?? 0,
                'closed_at' => now(),
            ]);
        }

        $allDone = $signal->orders()->whereIn('status', ['NEW', 'PARTIALLY_FILLED'])->count() === 0;
        if ($allDone) {
            $legs = $signal->legs->map(fn($leg) => [
                'symbol' => $leg->market,
                'side' => $leg->side,
                'price' => (float) $leg->price,
            ])->toArray();

            $orders = $signal->orders->map(fn($o) => [
                'executed_qty' => (float) $o->qty_exec,
            ])->toArray();

            $adapters = $signal->legs->map(fn($leg) => match ($leg->exchange) {
                'binance' => new MockBinanceAdapter(),
                'coinbase' => new MockCoinbaseAdapter(),
                default => new MockBinanceAdapter(),
            })->toArray();

            $qty = min($orders[0]['executed_qty'] ?? 0, $orders[1]['executed_qty'] ?? 0);
            $buy = $legs[0]['side'] === 'buy' ? 0 : 1;
            $sell = 1 - $buy;
            $buyPrice = $legs[$buy]['price'];
            $sellPrice = $legs[$sell]['price'];
            $fees = ($adapters[$buy]->fees['taker'] + $adapters[$sell]->fees['taker']) / 10000;
            $pnl = ($sellPrice - $buyPrice) * $qty - $fees * $qty * ($buyPrice + $sellPrice);

            $signal->update(['status' => 'FILLED', 'expected_pnl' => $pnl]);
        }
    }

    private function calculateBackoff(int $attempt, int $base = 100, int $cap = 1000): int
    {
        $exp = min($cap, $base * (2 ** $attempt));
        return random_int(0, $exp);
    }
}
