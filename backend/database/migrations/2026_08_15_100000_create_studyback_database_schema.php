<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration creates the complete Studyback database schema based on the
     * Database Design Document (Final). All tables, columns, foreign keys, indexes,
     * constraints, and relationships are implemented according to the design specification.
     *
     * Database Target: PostgreSQL
     * Source: docs/04-database-design.md
     */
    public function up(): void
    {
        // =====================================================================
        // 1. USERS TABLE
        // =====================================================================
        // Note: Laravel's default users table is modified to match the design
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->timestampsTz(); // created_at, updated_at
        });

        // =====================================================================
        // 2. MATERIALS TABLE
        // =====================================================================
        Schema::create('materials', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('original_filename', 255);
            $table->string('file_path', 500)->unique();
            $table->integer('file_size_bytes');
            $table->string('status', 20)->default('processing');
            $table->text('failed_reason')->nullable();
            $table->timestampsTz(); // created_at, updated_at

            // Constraints
            $table->check('file_size_bytes > 0', 'materials_file_size_bytes_check');
            $table->check("status IN ('processing', 'ready', 'failed')", 'materials_status_check');
        });

        // =====================================================================
        // 3. TOPICS TABLE
        // =====================================================================
        Schema::create('topics', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('material_id')
                ->constrained('materials')
                ->onDelete('cascade');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->smallInteger('order_index')->default(0);
            $table->timestampsTz(); // created_at, updated_at

            // Unique constraint
            $table->unique(['material_id', 'name'], 'topics_material_id_name_unique');
        });

        // =====================================================================
        // 4. SUBTOPICS TABLE
        // =====================================================================
        Schema::create('subtopics', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('topic_id')
                ->constrained('topics')
                ->onDelete('cascade');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->smallInteger('order_index')->default(0);
            $table->decimal('mastery_score', 5, 2)->default(0);
            $table->string('status', 20)->default('not_started');
            $table->timestampsTz(); // created_at, updated_at

            // Constraints
            $table->check('mastery_score BETWEEN 0 AND 100', 'subtopics_mastery_score_check');
            $table->check("status IN ('not_started', 'in_progress', 'needs_review', 'mastered')", 'subtopics_status_check');

            // Unique constraint
            $table->unique(['topic_id', 'name'], 'subtopics_topic_id_name_unique');
        });

        // =====================================================================
        // 5. CHUNKS TABLE
        // =====================================================================
        Schema::create('chunks', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('material_id')
                ->constrained('materials')
                ->onDelete('cascade');
            $table->foreignId('topic_id')
                ->constrained('topics')
                ->onDelete('cascade');
            $table->foreignId('subtopic_id')
                ->nullable()
                ->constrained('subtopics')
                ->onDelete('set null');
            $table->text('content');
            $table->integer('chunk_index');
            $table->timestampTz('created_at')->useCurrent(); // Immutable, no updated_at

            // Unique constraint
            $table->unique(['material_id', 'chunk_index'], 'chunks_material_id_chunk_index_unique');
        });

        // =====================================================================
        // 6. STUDY_SESSIONS TABLE
        // =====================================================================
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
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz(); // created_at, updated_at

            // Constraints
            $table->check("mode IN ('teach_me', 'quiz_me', 'review_weak_topics', 'guided_study_session')", 'study_sessions_mode_check');
            $table->check("difficulty IS NULL OR difficulty IN ('easy', 'medium', 'hard')", 'study_sessions_difficulty_check');
            $table->check("status IN ('active', 'completed')", 'study_sessions_status_check');
        });

        // =====================================================================
        // 7. STUDY_SESSION_TOPICS TABLE (Pivot)
        // =====================================================================
        Schema::create('study_session_topics', function (Blueprint $table) {
            $table->foreignId('study_session_id')
                ->constrained('study_sessions')
                ->onDelete('cascade');
            $table->foreignId('topic_id')
                ->constrained('topics')
                ->onDelete('cascade');
            $table->timestampTz('created_at')->useCurrent();

            // Composite primary key
            $table->primary(['study_session_id', 'topic_id'], 'study_session_topics_pkey');
        });

        // =====================================================================
        // 8. QUIZZES TABLE
        // =====================================================================
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
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz(); // created_at, updated_at

            // Constraints
            $table->check("difficulty IS NULL OR difficulty IN ('easy', 'medium', 'hard')", 'quizzes_difficulty_check');
            $table->check("status IN ('in_progress', 'completed')", 'quizzes_status_check');
            $table->check('total_questions >= 0', 'quizzes_total_questions_check');
            $table->check('correct_count IS NULL OR correct_count >= 0', 'quizzes_correct_count_check');
            $table->check('score IS NULL OR score BETWEEN 0 AND 100', 'quizzes_score_check');
        });

        // =====================================================================
        // 9. QUIZ_QUESTIONS TABLE
        // =====================================================================
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
            $table->jsonb('options')->nullable(); // JSONB for multiple choice options
            $table->text('correct_answer');
            $table->smallInteger('order_index')->default(0);
            $table->timestampTz('created_at')->useCurrent(); // Immutable, no updated_at

            // Constraint
            $table->check("question_type IN ('multiple_choice', 'true_false', 'short_answer')", 'quiz_questions_question_type_check');
        });

        // =====================================================================
        // 10. QUIZ_ANSWERS TABLE
        // =====================================================================
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('quiz_question_id')
                ->unique() // 1:1 relationship - one answer per question
                ->constrained('quiz_questions')
                ->onDelete('cascade');
            $table->text('submitted_answer');
            $table->boolean('is_correct');
            $table->text('ai_feedback')->nullable();
            $table->timestampTz('answered_at')->useCurrent();
        });

        // =====================================================================
        // INDEXES
        // =====================================================================
        // These indexes support the main query patterns defined in the Database Design

        // Materials indexes
        Schema::table('materials', function (Blueprint $table) {
            $table->index('user_id', 'idx_materials_user');
            $table->index(['user_id', 'status'], 'idx_materials_user_status');
        });

        // Topics indexes
        Schema::table('topics', function (Blueprint $table) {
            $table->index('material_id', 'idx_topics_material');
        });

        // Subtopics indexes
        Schema::table('subtopics', function (Blueprint $table) {
            $table->index('topic_id', 'idx_subtopics_topic');
            $table->index('status', 'idx_subtopics_status');
        });

        // Chunks indexes - critical for retrieval queries
        Schema::table('chunks', function (Blueprint $table) {
            $table->index(['material_id', 'topic_id'], 'idx_chunks_material_topic');
            $table->index(['material_id', 'subtopic_id'], 'idx_chunks_material_subtopic');
        });

        // Study sessions indexes
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->index(['user_id', 'material_id'], 'idx_study_sessions_user_material');
        });

        // Quizzes indexes
        Schema::table('quizzes', function (Blueprint $table) {
            $table->index('study_session_id', 'idx_quizzes_session');
        });

        // Quiz questions indexes
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->index('quiz_id', 'idx_quiz_questions_quiz');
            $table->index('subtopic_id', 'idx_quiz_questions_subtopic');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Tables are dropped in reverse dependency order to avoid foreign key constraint violations.
     */
    public function down(): void
    {
        // Drop tables in reverse order of dependencies
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('study_session_topics');
        Schema::dropIfExists('study_sessions');
        Schema::dropIfExists('chunks');
        Schema::dropIfExists('subtopics');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('users');
    }
};