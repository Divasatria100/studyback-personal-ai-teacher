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
     * Source: docs/04-database-design.md §4 (`materials`) and §6 (indexes).
     */
    public function up(): void
    {
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
            $table->timestamps(); // created_at, updated_at

            // Ownership scoping indexes (docs/04-database-design.md §6)
            $table->index('user_id', 'idx_materials_user');
            $table->index(['user_id', 'status'], 'idx_materials_user_status');
        });

        // CHECK constraints are applied AFTER the table is created.
        $this->addCheckConstraint('materials', 'materials_file_size_bytes_check', 'file_size_bytes > 0');
        $this->addCheckConstraint('materials', 'materials_status_check', "status IN ('processing', 'ready', 'failed')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
