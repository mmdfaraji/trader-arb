<?php
namespace App\Arbitrage;

use App\Arbitrage\ExchangeAdapter;

/**
 * Coordinates the multi phase arbitrage flow.
 *
 * Sequence Diagram (simplified)
 * ---------------------------------
 * A: ingest/validate
 *    Trader -> Engine : submit signal
 *    Engine -> Trader : ack/validate
 * B: account select & reserve
 *    Engine -> Exchanges : check balances/reserve
 * C: send & monitor
 *    Engine -> ExA : placeOrder
 *    Engine -> ExB : placeOrder
 *    loop polling/backoff
 *      Engine -> Ex : getOrder
 * D: partial/hedge
 *    alt partial
 *      Engine -> hedge Ex : placeOrder
 * E: compute & finalize
 *    Engine -> Trader : execution report
 */
class ArbitrageEngine
{
    public function __construct(
        private ExchangeAdapter $legA,
        private ExchangeAdapter $legB
    ) {
    }

    /** Execute the arbitrage legs. */
    public function run(array $signal, array $opts = []): array
    {
        // ----- Phase A: ingest/validate -----
        $constraints = $signal['constraints'] ?? [];
        $maxSlippage = $constraints['Max_slippage_bps'] ?? 0;
        $minPnl = $constraints['Min_expected_pnl'] ?? 0;

        // ----- Phase B: account select & reserve -----
        $qty = $this->calcExecutableQty($signal['legs'], $opts);

        // ----- Phase C: send & monitor -----
        $orders = [];
        foreach ($signal['legs'] as $i => $leg) {
            $adapter = $i === 0 ? $this->legA : $this->legB;
            $tif = $leg['tif'] ?? 'IOC';
            $qtyRounded = $this->roundQty($qty, $adapter);
            $orders[$i] = $adapter->placeOrder([
                'symbol' => $leg['symbol'],
                'side' => $leg['side'],
                'qty' => $qtyRounded,
                'price' => $leg['price'],
                'tif' => $tif,
            ]);
        }

        foreach ($orders as $i => $order) {
            $adapter = $i === 0 ? $this->legA : $this->legB;
            $orders[$i] = $this->pollOrder($adapter, $order['id']);
        }

        // ----- Phase D: partial/hedge -----
        $execA = $orders[0]['executed_qty'];
        $execB = $orders[1]['executed_qty'];
        if (abs($execA - $execB) > 0) {
            // hedge on exchange with lesser fill
            if ($execA > $execB) {
                $adapter = $this->legB;
                $hedgeQty = $execA - $execB;
                $adapter->placeOrder([
                    'symbol' => $signal['legs'][1]['symbol'],
                    'side' => $signal['legs'][1]['side'],
                    'qty' => $hedgeQty,
                    'price' => $signal['legs'][1]['price'],
                    'tif' => 'IOC',
                ]);
            } else {
                $adapter = $this->legA;
                $hedgeQty = $execB - $execA;
                $adapter->placeOrder([
                    'symbol' => $signal['legs'][0]['symbol'],
                    'side' => $signal['legs'][0]['side'],
                    'qty' => $hedgeQty,
                    'price' => $signal['legs'][0]['price'],
                    'tif' => 'IOC',
                ]);
            }
        }

        // ----- Phase E: compute & finalize -----
        $pnl = $this->computePnl($orders, [$this->legA, $this->legB], $signal['legs']);
        if ($pnl < $minPnl) {
            return ['status' => 'REJECTED', 'pnl' => $pnl];
        }
        return ['status' => 'FILLED', 'pnl' => $pnl];
    }

    /** qty_exec algorithm - min across balances, constraints, leg qty */
    private function calcExecutableQty(array $legs, array $opts): float
    {
        $balances = $opts['balances'] ?? [100 => PHP_FLOAT_MAX];
        $riskCap = $opts['risk_cap'] ?? PHP_FLOAT_MAX;
        $qtys = array_column($legs, 'qty');
        $qtys[] = $balances[0] ?? PHP_FLOAT_MAX;
        $qtys[] = $balances[1] ?? PHP_FLOAT_MAX;
        $qtys[] = $riskCap;
        return min($qtys);
    }

    private function roundQty(float $qty, ExchangeAdapter $adapter): float
    {
        $p = $adapter->precision ?? ['tick' => 1, 'step' => 1, 'pack' => 1];
        $qty = floor($qty / $p['step']) * $p['step'];
        $qty = floor($qty / $p['pack']) * $p['pack'];
        return $qty;
    }

    private function pollOrder(ExchangeAdapter $adapter, string $orderId): array
    {
        $attempt = 0;
        $order = $adapter->getOrder($orderId);
        while (in_array($order['status'], ['NEW', 'PARTIALLY_FILLED'], true) && $attempt < 5) {
            usleep($this->backoff($attempt) * 1000);
            $order = $adapter->getOrder($orderId);
            $attempt++;
        }
        if ($order['status'] === 'NEW') {
            $adapter->cancelOrder($orderId);
            $order['status'] = 'CANCELED';
        }
        return $order;
    }

    private function backoff(int $attempt, int $base = 100, int $cap = 1000): int
    {
        $exp = min($cap, $base * (2 ** $attempt));
        return random_int(0, $exp);
    }

    private function computePnl(array $orders, array $adapters, array $legs): float
    {
        $qty = min($orders[0]['executed_qty'], $orders[1]['executed_qty']);
        $buy = $legs[0]['side'] === 'buy' ? 0 : 1;
        $sell = 1 - $buy;
        $buyPrice = $legs[$buy]['price'];
        $sellPrice = $legs[$sell]['price'];
        $fees = ($adapters[$buy]->fees['taker'] + $adapters[$sell]->fees['taker']) / 10000;
        return ($sellPrice - $buyPrice) * $qty - $fees * $qty * ($buyPrice + $sellPrice);
    }
}
