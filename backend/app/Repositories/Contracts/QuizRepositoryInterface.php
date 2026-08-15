<?php

namespace App\Repositories\Contracts;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Collection;

interface QuizRepositoryInterface
{
    public function create(array $attributes): Quiz;

    public function findById(int $id): ?Quiz;

    public function findOwnedByUser(int $userId, int $quizId): ?Quiz;

    /**
     * Requestor-scoped question that belongs to a specific quiz.
     */
    public function findQuestionInQuiz(int $quizId, int $quizQuestionId): ?QuizQuestion;

    /**
     * Bulk-insert questions for a quiz.
     *
     * @param  list<array{subtopic_id: int, question_type: string, question_text: string, options?: array|null, correct_answer: string, order_index: int}>  $questionsData
     */
    public function insertQuestions(Quiz $quiz, array $questionsData): void;

    /**
     * @return Collection<int, QuizQuestion>
     */
    public function questionsWithAnswers(Quiz $quiz): Collection;

    public function answeredCount(Quiz $quiz): int;

    public function questions(Quiz $quiz): Collection;

    public function markCompleted(Quiz $quiz, int $correctCount): Quiz;
}