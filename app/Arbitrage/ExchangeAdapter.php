<?php
namespace App\Arbitrage;

/**
 * Exchange abstraction used by the arbitrage engine.
 */
interface ExchangeAdapter
{
    /**
     * Place an order on the exchange.
     *
     * @param array $order ['symbol'=>string,'side'=>string,'qty'=>float,'price'=>float,'tif'=>string]
     * @return array ['order_id'=>string,'status'=>string,'executed_qty'=>float,'price'=>float]
     */
    public function placeOrder(array $order): array;

    /** Cancel an existing order. */
    public function cancelOrder(string $orderId): array;

    /** Get order status. */
    public function getOrder(string $orderId): array;

    /** Get fills for an order. */
    public function getOrderFills(string $orderId): array;

    /** Subscribe to websocket channel. */
    public function wsSubscribe(string $channel, callable $callback): void;

    /** Get transcript of interactions for testing. */
    public function getTranscript(): array;
}
