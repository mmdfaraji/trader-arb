<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_fills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->unsignedInteger('fill_seq');
            $table->decimal('filled_qty', 24, 8);
            $table->decimal('price', 24, 8);
            $table->decimal('fee_amount', 24, 8)->nullable();
            $table->foreignId('fee_currency_id')->nullable()->constrained('currencies');
            $table->string('trade_id')->nullable();
            $table->timestamp('filled_at')->useCurrent();

            $table->unique(['order_id', 'fill_seq']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fills');
    }
};

