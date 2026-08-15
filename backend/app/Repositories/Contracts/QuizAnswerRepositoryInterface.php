<?php

namespace App\Repositories\Contracts;

use App\Models\QuizAnswer;

interface QuizAnswerRepositoryInterface
{
    public function hasAnswerForQuestion(int $quizQuestionId): bool;

    /**
     * Create one quiz answer row (insert-only, immutable historical log).
     */
    public function create(array $attributes): QuizAnswer;
}