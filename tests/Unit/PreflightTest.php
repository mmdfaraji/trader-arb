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

    public function test_compute_executable_qty_rejects_when_min_notional_not_met(): void
    {
        $service = new Preflight(feeBps: [], slippageBps: []);
        $balances = ['buy' => 5];
        $reserved = ['buy' => 0];
        $legs = [
            'buy' => [
                'side' => 'buy',
                'price' => 1,
                'qty' => 10_000,
                'min_notional' => 10,
            ],
        ];
        $qty = $service->computeExecutableQty($balances, $reserved, $legs);
        $this->assertSame(0.0, $qty);
    }

    public function test_compute_executable_qty_respects_max_qty(): void
    {
        $service = new Preflight(feeBps: [], slippageBps: []);
        $balances = ['sell' => 10_000];
        $reserved = ['sell' => 0];
        $legs = [
            'sell' => [
                'side' => 'sell',
                'price' => 1,
                'qty' => 10_000,
                'max_qty' => 1_000,
            ],
        ];
        $qty = $service->computeExecutableQty($balances, $reserved, $legs);
        $this->assertSame(1_000.0, $qty);

    public function test_rounds_exec_qty_and_price(): void
    {
        $service = new Preflight(feeBps: [], slippageBps: []);
        $result = $service->applyMarketConstraints(
            price: 100.3,
            execQty: 12.7,
            constraints: ['tick_size' => 0.5, 'step_size' => 1, 'pack_size' => 5]
        );

        $this->assertEqualsWithDelta(100.0, $result['price'], 0.0000001);
        $this->assertEqualsWithDelta(10, $result['qty'], 0.0000001);
    }

    public function test_min_notional_rejection(): void
    {
        $service = new Preflight(feeBps: [], slippageBps: []);

        $this->expectException(\InvalidArgumentException::class);
        $service->applyMarketConstraints(
            price: 100,
            execQty: 4,
            constraints: ['tick_size' => 1, 'step_size' => 1, 'min_notional' => 500]
        );
    }

    public function test_max_order_size_rejection(): void
    {
        $service = new Preflight(feeBps: [], slippageBps: []);

        $this->expectException(\InvalidArgumentException::class);
        $service->applyMarketConstraints(
            price: 100,
            execQty: 60,
            constraints: ['tick_size' => 1, 'step_size' => 1, 'max_order_size' => 50]
        );

    }
}
