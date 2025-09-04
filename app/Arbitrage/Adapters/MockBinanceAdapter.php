<?php
namespace App\Arbitrage\Adapters;

/** Mock adapter emulating Binance spot exchange. */
class MockBinanceAdapter extends MockExchange
{
    public array $fees = ['maker' => 1.0, 'taker' => 1.5]; // bps
    public array $precision = ['tick' => 0.01, 'step' => 0.001, 'pack' => 0.0001];
}
