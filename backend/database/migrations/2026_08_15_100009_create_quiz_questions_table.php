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
     * Source: docs/04-database-design.md §4 (`quiz_questions`) and §6 (indexes).
     * Quiz questions are immutable after creation, so only created_at exists.
     */
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('quiz_id')
                ->constrained('quizzes')
                ->onDelete('cascade');
            $table->foreignId('subtopic_id')
                ->constrained('subtopics')
                ->onDelete('cascade');
            $table->string('question_type', 20);
            $table->text('question_text');
            $table->jsonb('options')->nullable();
            $table->text('correct_answer');
            $table->smallInteger('order_index')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('quiz_id', 'idx_quiz_questions_quiz');
            $table->index('subtopic_id', 'idx_quiz_questions_subtopic');
        });

        // CHECK constraints are applied AFTER the table is created.
        $this->addCheckConstraint('quiz_questions', 'quiz_questions_question_type_check', "question_type IN ('multiple_choice', 'true_false', 'short_answer')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
