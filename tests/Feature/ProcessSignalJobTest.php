<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\Signal;
use App\Models\SignalLeg;
use App\Models\Exchange;
use App\Models\Currency;
use App\Models\Pair;
use App\Models\PairExchange;
use App\Jobs\ProcessSignalJob;

class ProcessSignalJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_signal_is_executed(): void
    {
        $exA = Exchange::create(['name' => 'exA', 'api_url' => '', 'ws_url' => '', 'status' => 'ACTIVE']);
        $exB = Exchange::create(['name' => 'exB', 'api_url' => '', 'ws_url' => '', 'status' => 'ACTIVE']);
        $usdt = Currency::create(['symbol' => 'USDT', 'name' => 'Tether']);
        $irr = Currency::create(['symbol' => 'IRR', 'name' => 'Rial']);
        $pair = Pair::create(['base_currency_id' => $usdt->id, 'quote_currency_id' => $irr->id, 'symbol' => 'USDT/IRR']);
        PairExchange::create([
            'exchange_id' => $exA->id,
            'pair_id' => $pair->id,
            'exchange_symbol' => 'USDT/IRR',
            'tick_size' => 1,
            'step_size' => 1,
            'min_notional' => 1,
            'maker_fee_bps' => 5,
            'taker_fee_bps' => 7,
            'slippage_bps' => 1,
            'status' => 'ACTIVE',
        ]);
        PairExchange::create([
            'exchange_id' => $exB->id,
            'pair_id' => $pair->id,
            'exchange_symbol' => 'USDT/IRR',
            'tick_size' => 1,
            'step_size' => 1,
            'min_notional' => 1,
            'maker_fee_bps' => 6,
            'taker_fee_bps' => 8,
            'slippage_bps' => 2,
            'status' => 'ACTIVE',
        ]);

        $signal = Signal::create([
            'id' => (string) Str::uuid(),
            'ttl_ms' => 5000,
            'status' => 'PENDING',
            'source' => 'api',
            'constraints' => ['Min_expected_pnl' => 0],
        ]);

        SignalLeg::create([
            'signal_id' => $signal->id,
            'exchange' => 'exA',
            'market' => 'USDT/IRR',
            'side' => 'buy',
            'price' => 1000000,
            'qty' => 10000,
            'time_in_force' => 'IOC',
        ]);

        SignalLeg::create([
            'signal_id' => $signal->id,
            'exchange' => 'exB',
            'market' => 'USDT/IRR',
            'side' => 'sell',
            'price' => 1010000,
            'qty' => 10000,
            'time_in_force' => 'IOC',
        ]);

        ProcessSignalJob::dispatchSync($signal->id);

        $signal->refresh();
        $this->assertSame('FILLED', $signal->status);
        $this->assertNotNull($signal->expected_pnl);
    }
}
