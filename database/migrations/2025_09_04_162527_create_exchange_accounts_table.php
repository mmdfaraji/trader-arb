<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('exchanges');
            $table->string('label');
            $table->string('api_key_ref');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['exchange_id', 'label']);
            $table->index(['exchange_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_accounts');
    }
};

