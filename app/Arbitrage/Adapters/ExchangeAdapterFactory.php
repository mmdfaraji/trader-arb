<?php
namespace App\Arbitrage\Adapters;

use App\Arbitrage\ExchangeAdapter;
use InvalidArgumentException;

/**
 * Factory for resolving exchange adapters by name.
 */
class ExchangeAdapterFactory
{
    /**
     * Mapping of exchange name => adapter class.
     *
     * @var array<string,class-string<ExchangeAdapter>>
     */
    protected static array $map = [
        'exA' => MockBinanceAdapter::class,
        'exB' => MockCoinbaseAdapter::class,
        'binance' => MockBinanceAdapter::class,
        'coinbase' => MockCoinbaseAdapter::class,
    ];

    /** @var array<string,ExchangeAdapter> */
    protected static array $instances = [];

    /** Resolve an adapter instance for the given exchange name. */
    public function make(string $exchange): ExchangeAdapter
    {
        $class = self::$map[$exchange] ?? null;
        if (!$class) {
            throw new InvalidArgumentException("No adapter registered for {$exchange}");
        }
        return self::$instances[$exchange] = new $class();
    }

    /** Retrieve a previously instantiated adapter (mainly for testing). */
    public static function get(string $exchange): ?ExchangeAdapter
    {
        return self::$instances[$exchange] ?? null;
    }

    /** Clear any cached adapter instances. */
    public static function reset(): void
    {
        self::$instances = [];
    }
}
