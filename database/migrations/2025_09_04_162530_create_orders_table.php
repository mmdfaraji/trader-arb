<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('signal_id');
            $table->foreignId('exchange_id')->constrained('exchanges');
            $table->foreignId('exchange_account_id')->constrained('exchange_accounts');
            $table->foreignId('pair_id')->constrained('pairs');
            $table->string('side');
            $table->string('type');
            $table->string('tif');
            $table->string('client_order_id');
            $table->string('exchange_order_id')->nullable();
            $table->decimal('price', 24, 8)->nullable();
            $table->decimal('qty', 24, 8);
            $table->decimal('qty_exec', 24, 8)->default(0);
            $table->decimal('notional', 24, 8)->nullable();
            $table->string('status');
            $table->decimal('filled_qty', 24, 8)->default(0);
            $table->decimal('avg_price', 24, 8)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->foreign('signal_id')->references('id')->on('signals')->cascadeOnDelete();
            $table->unique(['exchange_id', 'client_order_id']);
            $table->unique(['exchange_id', 'exchange_order_id']);
            $table->index(['exchange_account_id', 'status']);
            $table->index('pair_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

