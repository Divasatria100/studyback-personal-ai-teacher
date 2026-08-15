<?php

namespace App\Repositories\Eloquent;

use App\Models\QuizAnswer;
use App\Repositories\Contracts\QuizAnswerRepositoryInterface;

class EloquentQuizAnswerRepository implements QuizAnswerRepositoryInterface
{
    public function hasAnswerForQuestion(int $quizQuestionId): bool
    {
        return QuizAnswer::query()
            ->where('quiz_question_id', $quizQuestionId)
            ->exists();
    }

    public function create(array $attributes): QuizAnswer
    {
        return QuizAnswer::query()->create($attributes);
    }
}
