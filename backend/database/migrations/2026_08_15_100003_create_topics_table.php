<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Source: docs/04-database-design.md §4 (`topics`) and §6 (indexes).
     */
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id(); // BIGSERIAL PRIMARY KEY
            $table->foreignId('material_id')
                ->constrained('materials')
                ->onDelete('cascade');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->smallInteger('order_index')->default(0);
            $table->timestamps(); // created_at, updated_at

            // Prevent duplicate topic names within one material.
            $table->unique(['material_id', 'name'], 'topics_material_id_name_unique');
            $table->index('material_id', 'idx_topics_material');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
