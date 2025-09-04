<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_signal_is_persisted(): void
    {
        $payload = [
            'signal_id' => (string) Str::uuid(),
            'created_at' => Carbon::now()->toIso8601String(),
            'ttl_ms' => 5000,
            'legs' => [
                [
                    'Exchange' => 'ramzinex',
                    'market' => 'USDT/IRR',
                    'Side' => 'buy',
                    'Price' => 1000000,
                    'Qty' => 10000,
                    'time_in_force' => 'IOC',
                ],
            ],
            'constraints' => [
                'Max_slippage_bps' => 10,
            ],
        ];

        $response = $this->postJson('/api/signals', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('signals', ['id' => $payload['signal_id']]);
        $this->assertDatabaseCount('signal_legs', 1);
    }

    public function test_signal_ttl_expired_is_rejected(): void
    {
        $payload = [
            'signal_id' => (string) Str::uuid(),
            'created_at' => Carbon::now()->subSeconds(10)->toIso8601String(),
            'ttl_ms' => 5000,
            'legs' => [
                [
                    'Exchange' => 'ramzinex',
                    'market' => 'USDT/IRR',
                    'Side' => 'buy',
                    'Price' => 1000000,
                    'Qty' => 10000,
                    'time_in_force' => 'IOC',
                ],
            ],
        ];

        $response = $this->postJson('/api/signals', $payload);

        $response->assertStatus(422);
    }
}
