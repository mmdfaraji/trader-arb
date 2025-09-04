<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('exchanges');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->string('exchange_symbol');
            $table->integer('scale_override')->nullable();

            $table->unique(['exchange_id', 'currency_id']);
            $table->unique(['exchange_id', 'exchange_symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchanges');
    }
};

