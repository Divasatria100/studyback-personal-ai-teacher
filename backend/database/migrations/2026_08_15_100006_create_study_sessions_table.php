<?php

use App\Support\Database\AddsCheckConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use AddsCheckConstraints;

    /**
     * Run the migrations.
     *
     * Source: docs/04-database-design.md §4 (`study_sessions`) and §6 (indexes).
     */
    public function up(): void
    {
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->foreignId('material_id')
                ->constrained('materials')
                ->onDelete('cascade');
            $table->string('mode', 30);
            $table->string('difficulty', 10)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps(); // created_at, updated_at

            $table->index(['user_id', 'material_id'], 'idx_study_sessions_user_material');
        });

        // CHECK constraints are applied AFTER the table is created.
        $this->addCheckConstraint('study_sessions', 'study_sessions_mode_check', "mode IN ('teach_me', 'quiz_me', 'review_weak_topics', 'guided_study_session')");
        $this->addCheckConstraint('study_sessions', 'study_sessions_difficulty_check', "difficulty IS NULL OR difficulty IN ('easy', 'medium', 'hard')");
        $this->addCheckConstraint('study_sessions', 'study_sessions_status_check', "status IN ('active', 'completed')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_sessions');
    }
};
