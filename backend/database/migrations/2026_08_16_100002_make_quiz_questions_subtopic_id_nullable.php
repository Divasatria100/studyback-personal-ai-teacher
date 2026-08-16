<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Allow quiz questions that target a topic as a whole (topic-only learning
     * targets). Subtopic-bound questions keep their NOT NULL reference; topic-only
     * questions store NULL subtopic_id and resolve their target through the parent
     * quiz's topic_id.
     */
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('subtopic_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('subtopic_id')->nullable(false)->change();
        });
    }
};