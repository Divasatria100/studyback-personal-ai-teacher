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
     * Topic-level mastery/status for topic-only learning targets (topics that
     * have zero subtopics). Topics that own subtopics keep their Learning State
     * entirely on subtopics; these columns only carry meaning for topic-only
     * targets, mirroring the subtopic schema exactly.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->decimal('mastery_score', 5, 2)->default(0);
            $table->string('status', 20)->default('not_started');

            $table->index('status', 'idx_topics_status');
        });

        // CHECK constraints are applied AFTER the table is altered (PostgreSQL only).
        $this->addCheckConstraint('topics', 'topics_mastery_score_check', 'mastery_score BETWEEN 0 AND 100');
        $this->addCheckConstraint('topics', 'topics_status_check', "status IN ('not_started', 'in_progress', 'needs_review', 'mastered')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropIndex('idx_topics_status');
            $table->dropColumn(['mastery_score', 'status']);
        });
    }
};