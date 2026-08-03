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
        Schema::create('role_analysis_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_analysis_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('reviewed_payload');
            $table->json('ambiguity_responses');
            $table->json('assumption_decisions');
            $table->timestamp('applied_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_analysis_reviews');
    }
};
