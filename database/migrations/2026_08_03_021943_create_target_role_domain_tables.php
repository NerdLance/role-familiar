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
        Schema::create('target_roles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('source_type');
            $table->string('title')->nullable();
            $table->string('company_name')->nullable();
            $table->string('seniority')->nullable();
            $table->text('role_summary')->nullable();
            $table->longText('original_job_listing')->nullable();
            $table->timestamp('interview_at')->nullable();
            $table->text('biggest_concern')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'target_roles_user_created_idx');
            $table->index(['user_id', 'status', 'archived_at'], 'target_roles_user_status_archive_idx');
            $table->index(['user_id', 'archived_at', 'created_at'], 'target_roles_user_archive_created_idx');
        });

        Schema::create('role_responsibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_role_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('priority');
            $table->string('source');
            $table->text('source_evidence')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamp('user_confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['target_role_id', 'sort_order']);
        });

        Schema::create('role_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_role_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('priority');
            $table->text('source_evidence')->nullable();
            $table->string('source');
            $table->unsignedTinyInteger('user_confidence')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamp('user_confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['target_role_id', 'sort_order']);
        });

        Schema::create('role_analyses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('target_role_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('prompt_version');
            $table->string('provider');
            $table->string('model');
            $table->char('source_hash', 64);
            $table->json('structured_result')->nullable();
            $table->json('assumptions')->nullable();
            $table->json('usage_metadata')->nullable();
            $table->string('failure_category')->nullable();
            $table->string('failure_summary', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['target_role_id', 'status', 'created_at'], 'role_analyses_role_status_created_idx');
            $table->index(['status', 'created_at'], 'role_analyses_status_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_analyses');
        Schema::dropIfExists('role_competencies');
        Schema::dropIfExists('role_responsibilities');
        Schema::dropIfExists('target_roles');
    }
};
