<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('signal_legs', function (Blueprint $table) {
            $table->id();
            $table->uuid('signal_id');
            $table->string('exchange');
            $table->string('market');
            $table->string('side');
            $table->decimal('price', 24, 8);
            $table->decimal('qty', 24, 8);
            $table->string('time_in_force');

            $table->foreign('signal_id')->references('id')->on('signals')->cascadeOnDelete();
            $table->index('signal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signal_legs');
    }
};
