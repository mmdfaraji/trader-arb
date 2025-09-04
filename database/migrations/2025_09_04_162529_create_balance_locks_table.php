<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_account_id')->constrained('exchange_accounts');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('amount', 24, 8);
            $table->string('reason');
            $table->uuid('signal_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->foreign('signal_id')->references('id')->on('signals')->nullOnDelete();
            $table->index(['exchange_account_id', 'currency_id']);
            $table->index('signal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_locks');
    }
};

