<?php

namespace App\Repositories\Eloquent;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Repositories\Contracts\QuizRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class EloquentQuizRepository implements QuizRepositoryInterface
{
    public function create(array $attributes): Quiz
    {
        return Quiz::query()->create($attributes);
    }

    public function findById(int $id): ?Quiz
    {
        return Quiz::query()->find($id);
    }

    public function findOwnedByUser(int $userId, int $quizId): ?Quiz
    {
        return Quiz::query()
            ->whereHas('studySession', fn ($query) => $query->where('user_id', $userId))
            ->find($quizId);
    }

    public function findQuestionInQuiz(int $quizId, int $quizQuestionId): ?QuizQuestion
    {
        return QuizQuestion::query()
            ->where('quiz_id', $quizId)
            ->find($quizQuestionId);
    }

    public function insertQuestions(Quiz $quiz, array $questionsData): void
    {
        $rows = array_map(
            fn (array $data) => [
                'quiz_id' => $quiz->id,
                'subtopic_id' => $data['subtopic_id'],
                'question_type' => $data['question_type'],
                'question_text' => $data['question_text'],
                'options' => isset($data['options'])
                    ? json_encode(array_values($data['options']))
                    : null,
                'correct_answer' => $data['correct_answer'],
                'order_index' => $data['order_index'] ?? 0,
                'created_at' => Carbon::now(),
            ],
            $questionsData
        );

        QuizQuestion::query()->insert($rows);
    }

    public function questionsWithAnswers(Quiz $quiz): Collection
    {
        return QuizQuestion::query()
            ->with('answer')
            ->where('quiz_id', $quiz->id)
            ->orderBy('order_index')
            ->get();
    }

    public function questions(Quiz $quiz): Collection
    {
        return QuizQuestion::query()
            ->where('quiz_id', $quiz->id)
            ->orderBy('order_index')
            ->get();
    }

    public function answeredCount(Quiz $quiz): int
    {
        return QuizAnswer::query()
            ->whereHas(
                'quizQuestion',
                fn ($query) => $query->where('quiz_id', $quiz->id)
            )
            ->count();
    }

    public function markCompleted(Quiz $quiz, int $correctCount): Quiz
    {
        $quiz->forceFill([
            'correct_count' => $correctCount,
            'score' => $quiz->total_questions > 0
                ? round($correctCount / $quiz->total_questions * 100, 2)
                : 0,
            'status' => 'completed',
            'completed_at' => Carbon::now(),
        ])->save();

        return $quiz->refresh();
    }
}