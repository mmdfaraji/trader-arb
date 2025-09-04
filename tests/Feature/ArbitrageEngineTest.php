<?php
namespace Tests\Feature;

use App\Arbitrage\Adapters\MockBinanceAdapter;
use App\Arbitrage\Adapters\MockCoinbaseAdapter;
use App\Arbitrage\ArbitrageEngine;
use PHPUnit\Framework\TestCase;

class ArbitrageEngineTest extends TestCase
{
    public function test_full_fill_flow(): void
    {
        $exA = new MockBinanceAdapter();
        $exB = new MockCoinbaseAdapter();
        $engine = new ArbitrageEngine($exA, $exB);

        $signal = [
            'legs' => [
                ['symbol' => 'BTCUSDT', 'side' => 'buy', 'qty' => 1, 'price' => 100.0, 'tif' => 'IOC'],
                ['symbol' => 'BTCUSDT', 'side' => 'sell', 'qty' => 1, 'price' => 101.0, 'tif' => 'IOC'],
            ],
        ];

        $report = $engine->run($signal);
        $this->assertSame('FILLED', $report['status']);
        $this->assertGreaterThan(0, $report['pnl']);
        $this->assertNotEmpty($exA->getTranscript());
    }

    public function test_timeout_causes_cancel(): void
    {
        $exA = new MockBinanceAdapter();
        $exB = new MockCoinbaseAdapter();
        $exA->setScenario('BTCUSDT', ['action' => 'timeout']);
        $engine = new ArbitrageEngine($exA, $exB);

        $signal = [
            'legs' => [
                ['symbol' => 'BTCUSDT', 'side' => 'buy', 'qty' => 1, 'price' => 100.0],
                ['symbol' => 'BTCUSDT', 'side' => 'sell', 'qty' => 1, 'price' => 101.0],
            ],
        ];

        $report = $engine->run($signal);
        $trans = $exA->getTranscript();
        $found = false;
        foreach ($trans as $t) {
            if (isset($t['cancelOrder'])) {
                $found = true;
            }
        }
        $this->assertTrue($found);
        $this->assertSame('FILLED', $report['status']);
    }

    public function test_partial_fill_triggers_hedge(): void
    {
        $exA = new MockBinanceAdapter();
        $exB = new MockCoinbaseAdapter();
        $exA->setScenario('BTCUSDT', ['action' => 'partial', 'qty' => 0.4]);
        $engine = new ArbitrageEngine($exA, $exB);

        $signal = [
            'legs' => [
                ['symbol' => 'BTCUSDT', 'side' => 'buy', 'qty' => 1, 'price' => 100.0],
                ['symbol' => 'BTCUSDT', 'side' => 'sell', 'qty' => 1, 'price' => 101.0],
            ],
        ];

        $engine->run($signal);
        $transcript = $exB->getTranscript();
        $this->assertGreaterThan(1, count($transcript)); // hedge order added
    }
}
