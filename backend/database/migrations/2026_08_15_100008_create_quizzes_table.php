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
     * Source: docs/04-database-design.md §4 (`quizzes`) and §6 (indexes).
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('study_session_id')
                ->constrained('study_sessions')
                ->onDelete('cascade');
            $table->foreignId('topic_id')
                ->constrained('topics')
                ->onDelete('cascade');
            $table->foreignId('subtopic_id')
                ->nullable()
                ->constrained('subtopics')
                ->onDelete('cascade');
            $table->string('difficulty', 10)->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->smallInteger('total_questions')->default(0);
            $table->smallInteger('correct_count')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps(); // created_at, updated_at

            $table->index('study_session_id', 'idx_quizzes_session');
        });

        // CHECK constraints are applied AFTER the table is created.
        $this->addCheckConstraint('quizzes', 'quizzes_difficulty_check', "difficulty IS NULL OR difficulty IN ('easy', 'medium', 'hard')");
        $this->addCheckConstraint('quizzes', 'quizzes_status_check', "status IN ('in_progress', 'completed')");
        $this->addCheckConstraint('quizzes', 'quizzes_total_questions_check', 'total_questions >= 0');
        $this->addCheckConstraint('quizzes', 'quizzes_correct_count_check', 'correct_count IS NULL OR correct_count >= 0');
        $this->addCheckConstraint('quizzes', 'quizzes_score_check', 'score IS NULL OR score BETWEEN 0 AND 100');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
