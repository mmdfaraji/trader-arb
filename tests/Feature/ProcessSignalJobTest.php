<?php

namespace Tests\Feature;

use App\Arbitrage\Adapters\ExchangeAdapterFactory;
use App\Arbitrage\Adapters\MockBinanceAdapter;
use App\Arbitrage\Adapters\MockCoinbaseAdapter;
use App\Jobs\ProcessSignalJob;
use App\Models\Balance;
use App\Models\Currency;
use App\Models\Exchange;
use App\Models\ExchangeAccount;
use App\Models\Pair;
use App\Models\PairExchange;
use App\Models\Signal;
use App\Models\SignalLeg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessSignalJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_signal_is_executed(): void
    {
        ExchangeAdapterFactory::reset();
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

        $acctA = ExchangeAccount::create([
            'exchange_id' => $exA->id,
            'label' => 'a',
            'api_key_ref' => 'a',
            'is_primary' => true,
            'created_at' => now(),
        ]);
        $acctB = ExchangeAccount::create([
            'exchange_id' => $exB->id,
            'label' => 'b',
            'api_key_ref' => 'b',
            'is_primary' => true,
            'created_at' => now(),
        ]);
        foreach ([$acctA->id, $acctB->id] as $acctId) {
            Balance::create([
                'exchange_account_id' => $acctId,
                'currency_id' => $irr->id,
                'available' => 20_000_000_000,
                'reserved' => 0,
            ]);
            Balance::create([
                'exchange_account_id' => $acctId,
                'currency_id' => $usdt->id,
                'available' => 20_000,
                'reserved' => 0,
            ]);
        }

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

    public function test_signal_rejected_when_insufficient_balance(): void
    {
        ExchangeAdapterFactory::reset();
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

        $acctA = ExchangeAccount::create([
            'exchange_id' => $exA->id,
            'label' => 'a',
            'api_key_ref' => 'a',
            'is_primary' => true,
            'created_at' => now(),
        ]);
        $acctB = ExchangeAccount::create([
            'exchange_id' => $exB->id,
            'label' => 'b',
            'api_key_ref' => 'b',
            'is_primary' => true,
            'created_at' => now(),
        ]);
        Balance::create([
            'exchange_account_id' => $acctA->id,
            'currency_id' => $irr->id,
            'available' => 20_000_000_000,
            'reserved' => 0,
        ]);
        Balance::create([
            'exchange_account_id' => $acctB->id,
            'currency_id' => $usdt->id,
            'available' => 1_000,
            'reserved' => 0,
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
        $this->assertSame('REJECTED', $signal->status);
    }

    public function test_signal_rejected_when_balance_reserved(): void
    {
        ExchangeAdapterFactory::reset();
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

        $acctA = ExchangeAccount::create([
            'exchange_id' => $exA->id,
            'label' => 'a',
            'api_key_ref' => 'a',
            'is_primary' => true,
            'created_at' => now(),
        ]);
        $acctB = ExchangeAccount::create([
            'exchange_id' => $exB->id,
            'label' => 'b',
            'api_key_ref' => 'b',
            'is_primary' => true,
            'created_at' => now(),
        ]);
        Balance::create([
            'exchange_account_id' => $acctA->id,
            'currency_id' => $irr->id,
            'available' => 20_000_000_000,
            'reserved' => 0,
        ]);
        Balance::create([
            'exchange_account_id' => $acctA->id,
            'currency_id' => $usdt->id,
            'available' => 20_000,
            'reserved' => 0,
        ]);
        Balance::create([
            'exchange_account_id' => $acctB->id,
            'currency_id' => $irr->id,
            'available' => 20_000_000_000,
            'reserved' => 0,
        ]);
        Balance::create([
            'exchange_account_id' => $acctB->id,
            'currency_id' => $usdt->id,
            'available' => 10_000,
            'reserved' => 9_500,
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
        $this->assertSame('REJECTED', $signal->status);
    }

    /**
     * @dataProvider exchangeCombinations
     */
    public function test_signal_uses_correct_adapters(string $buyEx, string $sellEx, string $buyClass, string $sellClass): void
    {
        ExchangeAdapterFactory::reset();
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

        $acctA = ExchangeAccount::create([
            'exchange_id' => $exA->id,
            'label' => 'a',
            'api_key_ref' => 'a',
            'is_primary' => true,
            'created_at' => now(),
        ]);
        $acctB = ExchangeAccount::create([
            'exchange_id' => $exB->id,
            'label' => 'b',
            'api_key_ref' => 'b',
            'is_primary' => true,
            'created_at' => now(),
        ]);
        foreach ([$acctA->id, $acctB->id] as $acctId) {
            Balance::create([
                'exchange_account_id' => $acctId,
                'currency_id' => $irr->id,
                'available' => 20_000_000_000,
                'reserved' => 0,
            ]);
            Balance::create([
                'exchange_account_id' => $acctId,
                'currency_id' => $usdt->id,
                'available' => 20_000,
                'reserved' => 0,
            ]);
        }

        $signal = Signal::create([
            'id' => (string) Str::uuid(),
            'ttl_ms' => 5000,
            'status' => 'PENDING',
            'source' => 'api',
            'constraints' => ['Min_expected_pnl' => 0],
        ]);

        SignalLeg::create([
            'signal_id' => $signal->id,
            'exchange' => $buyEx,
            'market' => 'USDT/IRR',
            'side' => 'buy',
            'price' => 1000000,
            'qty' => 10000,
            'time_in_force' => 'IOC',
        ]);

        SignalLeg::create([
            'signal_id' => $signal->id,
            'exchange' => $sellEx,
            'market' => 'USDT/IRR',
            'side' => 'sell',
            'price' => 1010000,
            'qty' => 10000,
            'time_in_force' => 'IOC',
        ]);

        ProcessSignalJob::dispatchSync($signal->id);

        $this->assertInstanceOf($buyClass, ExchangeAdapterFactory::get($buyEx));
        $this->assertInstanceOf($sellClass, ExchangeAdapterFactory::get($sellEx));
    }

    public static function exchangeCombinations(): array
    {
        return [
            ['exA', 'exB', MockBinanceAdapter::class, MockCoinbaseAdapter::class],
            ['exB', 'exA', MockCoinbaseAdapter::class, MockBinanceAdapter::class],
        ];
    }

    /**
     * @dataProvider riskFailureConstraints
     */
    public function test_signal_rejected_when_risk_checks_fail(array $constraints): void
    {
        ExchangeAdapterFactory::reset();
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
            'constraints' => $constraints,
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
        $this->assertSame('REJECTED', $signal->status);
    }

    public static function riskFailureConstraints(): array
    {
        return [
            'portfolio' => [['Min_expected_pnl' => 0, 'Max_portfolio_notional' => 1]],
            'exchange' => [['Min_expected_pnl' => 0, 'Max_portfolio_notional' => 1e12, 'Exchange_notional_caps' => ['exA' => 1]]],
            'market' => [['Min_expected_pnl' => 0, 'Max_portfolio_notional' => 1e12, 'Market_notional_caps' => ['USDT/IRR' => 1]]],
            'volatility' => [['Min_expected_pnl' => 0, 'Max_portfolio_notional' => 1e12, 'Max_price_move_pct' => 0.5]],
        ];
    }
}
