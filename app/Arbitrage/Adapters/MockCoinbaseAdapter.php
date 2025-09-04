<?php
namespace App\Arbitrage\Adapters;

/** Mock adapter emulating Coinbase spot exchange. */
class MockCoinbaseAdapter extends MockExchange
{
    public array $fees = ['maker' => 1.2, 'taker' => 1.8];
    public array $precision = ['tick' => 0.05, 'step' => 0.0001, 'pack' => 0.01];
}
