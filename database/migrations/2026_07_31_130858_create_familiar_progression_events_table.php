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
        Schema::create('familiar_progression_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_familiar_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->json('metadata');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('familiar_progression_events');
    }
};
