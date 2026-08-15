<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Source: docs/04-database-design.md §4 (`chunks`) and §6 (indexes).
     * Chunks are immutable, so only created_at exists (no updated_at).
     */
    public function up(): void
    {
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
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['material_id', 'chunk_index'], 'chunks_material_id_chunk_index_unique');
            $table->index(['material_id', 'topic_id'], 'idx_chunks_material_topic');
            $table->index(['material_id', 'subtopic_id'], 'idx_chunks_material_subtopic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
