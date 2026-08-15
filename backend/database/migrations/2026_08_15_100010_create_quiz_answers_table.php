<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Source: docs/04-database-design.md §4 (`quiz_answers`).
     * Answers are immutable; only answered_at is stored (no created_at/updated_at).
     */
    public function up(): void
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('quiz_question_id')
                ->unique() // 1:1 relationship — one answer per question
                ->constrained('quiz_questions')
                ->onDelete('cascade');
            $table->text('submitted_answer');
            $table->boolean('is_correct');
            $table->text('ai_feedback')->nullable();
            $table->timestamp('answered_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
