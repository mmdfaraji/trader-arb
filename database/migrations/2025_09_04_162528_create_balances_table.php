<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_account_id')->constrained('exchange_accounts');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('available', 24, 8)->default(0);
            $table->decimal('reserved', 24, 8)->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['exchange_account_id', 'currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};

