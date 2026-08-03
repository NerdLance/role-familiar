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
        Schema::table('user_familiars', function (Blueprint $table) {
            $table->index('user_id');
            $table->dropUnique(['user_id']);
            $table->foreignId('target_role_id')
                ->nullable()
                ->after('user_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_familiars', function (Blueprint $table) {
            $table->dropForeign(['target_role_id']);
            $table->dropUnique(['target_role_id']);
            $table->dropColumn('target_role_id');
            $table->dropIndex(['user_id']);
            $table->unique('user_id');
        });
    }
};
