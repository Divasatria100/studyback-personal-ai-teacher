<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Source: docs/04-database-design.md §4 (`study_session_topics`).
     * Pivot table with a composite primary key (study_session_id, topic_id).
     */
    public function up(): void
    {
        Schema::create('study_session_topics', function (Blueprint $table) {
            $table->foreignId('study_session_id')
                ->constrained('study_sessions')
                ->onDelete('cascade');
            $table->foreignId('topic_id')
                ->constrained('topics')
                ->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['study_session_id', 'topic_id'], 'study_session_topics_pkey');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_session_topics');
    }
};
