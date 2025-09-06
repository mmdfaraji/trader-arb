<?php

namespace App\Services;

class Preflight
{
    public function __construct(
        private array $feeBps,
        private array $slippageBps
    ) {}

    public function expectedNetPnl(array $legs, float $execQty): float
    {
        $buyPrice = $legs['buy'];
        $sellPrice = $legs['sell'];
        $gross = ($sellPrice - $buyPrice) * $execQty;
        $feeCost = ($buyPrice * $execQty * ($this->feeBps['buy'] ?? 0) / 10000)
            + ($sellPrice * $execQty * ($this->feeBps['sell'] ?? 0) / 10000);

        return $gross - $feeCost;
    }

    /**
     * Determine executable quantity based on account balances, reservations, and leg rules.
     *
     * @param  array  $balances  Available balance per leg key
     * @param  array  $reserved  Reserved amounts per leg key
     * @param  array  $legs  Leg definitions including side, price, qty, and optional constraints
     */
    public function computeExecutableQty(array $balances, array $reserved, array $legs): float
    {
        $qty = INF;
        foreach ($legs as $key => $leg) {
            $available = ($balances[$key] ?? 0) - ($reserved[$key] ?? 0);
            $available = max(0, $available);

            if (($leg['side'] ?? '') === 'buy') {
                $maxByBalance = $available / ($leg['price'] ?? 1);
            } else {
                $maxByBalance = $available;
            }

            $maxByBalance = min($maxByBalance, $leg['qty'] ?? PHP_FLOAT_MAX);

            if (isset($leg['max_qty'])) {
                $maxByBalance = min($maxByBalance, $leg['max_qty']);
            }

            if (isset($leg['min_notional']) && $leg['min_notional'] > 0) {
                if ($maxByBalance * ($leg['price'] ?? 0) < $leg['min_notional']) {
                    return 0.0;
                }
            }

            $qty = min($qty, $maxByBalance);
        }

        return $qty === INF ? 0.0 : max(0.0, $qty);
    }

    public function expectedNetPnlWithSlippage(array $legs, float $execQty): float
    {
        $buyPrice = $legs['buy'] * (1 + ($this->slippageBps['buy'] ?? 0) / 10000);
        $sellPrice = $legs['sell'] * (1 - ($this->slippageBps['sell'] ?? 0) / 10000);
        $gross = ($sellPrice - $buyPrice) * $execQty;
        $feeCost = ($buyPrice * $execQty * ($this->feeBps['buy'] ?? 0) / 10000)
            + ($sellPrice * $execQty * ($this->feeBps['sell'] ?? 0) / 10000);

        return $gross - $feeCost;
    }

    public function passesMinPnl(float $pnlWithBuffers, float $minExpected): bool
    {
        return $pnlWithBuffers >= $minExpected;
    }
}
