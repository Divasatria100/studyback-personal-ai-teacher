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
     * Source: docs/04-database-design.md §4 (`subtopics`) and §6 (indexes).
     */
    public function up(): void
    {
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
            $table->timestamps(); // created_at, updated_at

            // Prevent duplicate subtopic names within one topic.
            $table->unique(['topic_id', 'name'], 'subtopics_topic_id_name_unique');
            $table->index('topic_id', 'idx_subtopics_topic');
            $table->index('status', 'idx_subtopics_status');
        });

        // CHECK constraints are applied AFTER the table is created.
        $this->addCheckConstraint('subtopics', 'subtopics_mastery_score_check', 'mastery_score BETWEEN 0 AND 100');
        $this->addCheckConstraint('subtopics', 'subtopics_status_check', "status IN ('not_started', 'in_progress', 'needs_review', 'mastered')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtopics');
    }
};
