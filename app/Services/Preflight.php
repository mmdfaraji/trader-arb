<?php

namespace App\Services;

class Preflight
{
    public function __construct(
        private int $feeBps,
        private int $slippageBps
    ) {}

    public function expectedNetPnl(array $legs, float $execQty): float
    {
        $buyPrice = $legs['buy'];
        $sellPrice = $legs['sell'];
        $gross = ($sellPrice - $buyPrice) * $execQty;
        $feeCost = ($buyPrice * $execQty + $sellPrice * $execQty) * $this->feeBps / 10000;
        return $gross - $feeCost;
    }

    public function expectedNetPnlWithSlippage(array $legs, float $execQty): float
    {
        $buyPrice = $legs['buy'] * (1 + $this->slippageBps / 10000);
        $sellPrice = $legs['sell'] * (1 - $this->slippageBps / 10000);
        $gross = ($sellPrice - $buyPrice) * $execQty;
        $feeCost = ($buyPrice * $execQty + $sellPrice * $execQty) * $this->feeBps / 10000;
        return $gross - $feeCost;
    }

    public function passesMinPnl(float $pnlWithBuffers, float $minExpected): bool
    {
        return $pnlWithBuffers >= $minExpected;
    }
}
