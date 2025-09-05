<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pair_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('exchanges');
            $table->foreignId('pair_id')->constrained('pairs');
            $table->string('exchange_symbol');
            $table->decimal('tick_size', 24, 8);
            $table->decimal('step_size', 24, 8);
            $table->decimal('min_notional', 24, 8);
            $table->decimal('max_order_size', 24, 8)->nullable();
            $table->decimal('pack_size', 24, 8)->nullable();
            $table->unsignedInteger('maker_fee_bps')->nullable();
            $table->unsignedInteger('taker_fee_bps')->nullable();
            $table->unsignedInteger('slippage_bps')->nullable();
            $table->string('status');

            $table->unique(['exchange_id', 'pair_id']);
            $table->unique(['exchange_id', 'exchange_symbol']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pair_exchanges');
    }
};

