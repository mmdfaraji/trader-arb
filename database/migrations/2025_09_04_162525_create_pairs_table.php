<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_currency_id')->constrained('currencies');
            $table->foreignId('quote_currency_id')->constrained('currencies');
            $table->string('symbol')->unique();

            $table->unique(['base_currency_id', 'quote_currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairs');
    }
};

