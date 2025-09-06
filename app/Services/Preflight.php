<?php

namespace App\Services;

use InvalidArgumentException;

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


    public function applyMarketConstraints(float $price, float $execQty, array $constraints): array
    {
        $qty = $this->roundQty(
            $execQty,
            $constraints['step_size'] ?? null,
            $constraints['pack_size'] ?? null
        );
        $price = $this->roundPrice($price, $constraints['tick_size'] ?? null);

        $notional = $price * $qty;
        if (($constraints['min_notional'] ?? 0) > 0 && $notional < $constraints['min_notional']) {
            throw new InvalidArgumentException('Order notional below minimum');
        }

        if (($constraints['max_order_size'] ?? 0) > 0 && $qty > $constraints['max_order_size']) {
            throw new InvalidArgumentException('Order size exceeds maximum');
        }

        return ['price' => $price, 'qty' => $qty];
    }

    private function roundQty(float $qty, ?float $stepSize, ?float $packSize): float
    {
        if ($stepSize && $stepSize > 0) {
            $qty = floor($qty / $stepSize) * $stepSize;
        }
        if ($packSize && $packSize > 0) {
            $qty = floor($qty / $packSize) * $packSize;
        }

        return $qty;
    }

    private function roundPrice(float $price, ?float $tickSize): float
    {
        return ($tickSize && $tickSize > 0)
            ? floor($price / $tickSize) * $tickSize
            : $price;
    }
    public function passesPortfolioCap(float $notional, float $cap): bool
    {
        if ($cap <= 0) {
            return true;
        }

        return $notional <= $cap;
    }

    /**
     * @param  array<string,float>  $caps  keyed by exchange name
     */
    public function passesExchangeLimit(string $exchange, float $notional, array $caps): bool
    {
        $cap = $caps[$exchange] ?? 0;
        if ($cap <= 0) {
            return true;
        }

        return $notional <= $cap;
    }

    /**
     * @param  array<string,float>  $caps  keyed by market symbol
     */
    public function passesMarketLimit(string $market, float $notional, array $caps): bool
    {
        $cap = $caps[$market] ?? 0;
        if ($cap <= 0) {
            return true;
        }

        return $notional <= $cap;
    }

    public function passesVolatilityGuard(float $pctMove, float $threshold): bool
    {
        if ($threshold <= 0) {
            return true;
        }

        return abs($pctMove) <= $threshold;
    }
}
