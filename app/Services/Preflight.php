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
