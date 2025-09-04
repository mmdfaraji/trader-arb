<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('signal_id');
            $table->string('final_state');
            $table->decimal('net_position_delta', 24, 8)->nullable();
            $table->decimal('pnl_realized', 24, 8)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('slippage_bps', 24, 8)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('signal_id')->references('id')->on('signals')->cascadeOnDelete();
            $table->index('signal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_reports');
    }
};

