<?php

namespace Tests\Unit;

use App\Services\Preflight;
use PHPUnit\Framework\TestCase;

class PreflightTest extends TestCase
{
    public function test_expected_pnl_and_min_pnl_rejection(): void
    {
        $service = new Preflight(feeBps: ['buy' => 10, 'sell' => 10], slippageBps: ['buy' => 10, 'sell' => 10]);
        $legs = ['buy' => 1_000_000, 'sell' => 1_010_000];
        $execQty = 5119; // derived effective quantity

        $pnlBefore = $service->expectedNetPnl($legs, $execQty);
        $this->assertEqualsWithDelta(40_899_000, $pnlBefore, 2_000, 'PNL before buffers should match example');

        $pnlAfter = $service->expectedNetPnlWithSlippage($legs, $execQty);
        $this->assertFalse($service->passesMinPnl($pnlAfter, 35_000_000));
    }

    public function test_compute_executable_qty_considers_balances(): void
    {
        $service = new Preflight(feeBps: [], slippageBps: []);
        $balances = ['buy' => 10_000_000_000, 'sell' => 6_000];
        $reserved = ['buy' => 0, 'sell' => 1_000];
        $legs = [
            'buy' => ['side' => 'buy', 'price' => 1_000_000, 'qty' => 10_000],
            'sell' => ['side' => 'sell', 'price' => 1_010_000, 'qty' => 10_000],
        ];
        $qty = $service->computeExecutableQty($balances, $reserved, $legs);
        $this->assertSame(5000.0, $qty);
    }
}
