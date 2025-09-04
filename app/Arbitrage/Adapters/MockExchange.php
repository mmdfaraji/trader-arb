<?php
namespace App\Arbitrage\Adapters;

use App\Arbitrage\ExchangeAdapter;
use Ramsey\Uuid\Uuid;

/**
 * In-memory mock exchange used for tests.
 */
class MockExchange implements ExchangeAdapter
{
    protected array $orders = [];
    protected array $fills = [];
    protected array $transcript = [];
    protected array $scenarios = [];

    /**
     * Configure how the next order for a symbol should behave.
     * Example scenario: ['action' => 'fill', 'qty' => 1.0]
     */
    public function setScenario(string $symbol, array $scenario): void
    {
        $this->scenarios[$symbol] = $scenario;
    }

    public function placeOrder(array $order): array
    {
        $orderId = (string) Uuid::uuid4();
        $scenario = $this->scenarios[$order['symbol']] ?? ['action' => 'fill'];
        $status = 'NEW';
        $executed = 0.0;
        if ($scenario['action'] === 'fill') {
            $status = 'FILLED';
            $executed = $order['qty'];
            $this->fills[$orderId][] = [
                'qty' => $executed,
                'price' => $order['price'],
            ];
        } elseif ($scenario['action'] === 'partial') {
            $status = 'PARTIALLY_FILLED';
            $executed = $scenario['qty'];
            $this->fills[$orderId][] = [
                'qty' => $executed,
                'price' => $order['price'],
            ];
        } elseif ($scenario['action'] === 'timeout') {
            // stays NEW until polling times out
        }

        $this->orders[$orderId] = [
            'id' => $orderId,
            'symbol' => $order['symbol'],
            'side' => $order['side'],
            'qty' => $order['qty'],
            'price' => $order['price'],
            'status' => $status,
            'executed_qty' => $executed,
        ];

        $this->transcript[] = ['placeOrder' => $order];
        return $this->orders[$orderId];
    }

    public function cancelOrder(string $orderId): array
    {
        if (isset($this->orders[$orderId])) {
            $this->orders[$orderId]['status'] = 'CANCELED';
        }
        $this->transcript[] = ['cancelOrder' => $orderId];
        return $this->orders[$orderId] ?? [];
    }

    public function getOrder(string $orderId): array
    {
        $this->transcript[] = ['getOrder' => $orderId];
        return $this->orders[$orderId] ?? [];
    }

    public function getOrderFills(string $orderId): array
    {
        $this->transcript[] = ['getOrderFills' => $orderId];
        return $this->fills[$orderId] ?? [];
    }

    public function wsSubscribe(string $channel, callable $callback): void
    {
        $this->transcript[] = ['wsSubscribe' => $channel];
    }

    public function getTranscript(): array
    {
        return $this->transcript;
    }
}
