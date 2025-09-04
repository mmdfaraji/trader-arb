<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hedge_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('signal_id');
            $table->string('cause');
            $table->foreignId('from_order_id')->nullable()->constrained('orders');
            $table->foreignId('hedge_order_id')->nullable()->constrained('orders');
            $table->decimal('qty', 24, 8);
            $table->string('status');
            $table->json('result_details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('signal_id')->references('id')->on('signals')->cascadeOnDelete();
            $table->index('signal_id');
            $table->index('from_order_id');
            $table->index('hedge_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hedge_actions');
    }
};

