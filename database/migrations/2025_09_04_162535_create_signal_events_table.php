<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signal_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('signal_id');
            $table->string('event');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('signal_id')->references('id')->on('signals')->cascadeOnDelete();
            $table->index('signal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_events');
    }
};

