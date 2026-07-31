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
        Schema::create('familiar_quest_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_familiar_id')->constrained()->cascadeOnDelete();
            $table->string('quest_key');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_familiar_id', 'quest_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('familiar_quest_progress');
    }
};
