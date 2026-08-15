<?php

namespace App\Services\Quizzes;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudySession;
use App\Repositories\Contracts\ChunkRepositoryInterface;
use App\Repositories\Contracts\QuizAnswerRepositoryInterface;
use App\Repositories\Contracts\QuizRepositoryInterface;
use App\Repositories\Contracts\SubtopicRepositoryInterface;
use App\Services\Ai\Contracts\AiServiceInterface;
use App\Services\Quizzes\Exceptions\InsufficientContextException;
use App\Services\Quizzes\Exceptions\QuizAnswerConflictException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Quiz application module (API Design §12): generation, retrieval, and answer
 * submission + evaluation. Learning State is updated deterministically by
 * Laravel only — the AI verdict is input, never the authority on mastery.
 */
final class QuizService
{
    public function __construct(
        private readonly QuizRepositoryInterface $quizzes,
        private readonly QuizAnswerRepositoryInterface $answers,
        private readonly SubtopicRepositoryInterface $subtopics,
        private readonly ChunkRepositoryInterface $chunks,
        private readonly AiServiceInterface $ai,
    ) {}

    public function findOwnedByUser(int $userId, int $quizId): ?Quiz
    {
        return $this->quizzes->findOwnedByUser($userId, $quizId);
    }

    public function findQuestionInQuiz(int $quizId, int $quizQuestionId): ?QuizQuestion
    {
        return $this->quizzes->findQuestionInQuiz($quizId, $quizQuestionId);
    }

    /**
     * Generate a quiz for the session's topic scope (API Design §12).
     *
     * @param  int  $subtopicId  nullable subtopic scope; when present, must belong to $topicId
     * @param  list<array{id: int, name: string}>  $subtopicReference  subtopics available in the topic scope
     */
    public function generate(
        StudySession $session,
        int $topicId,
        ?int $subtopicId,
        string $difficulty,
        int $questionCount,
        array $subtopicReference = []
    ): Quiz {
        $context = $this->chunks->retrieveContext(
            $session->material_id,
            $topicId,
            $subtopicId
        );

        if ($context === []) {
            throw new InsufficientContextException;
        }

        $validSubtopicIds = $subtopicReference === []
            ? []
            : $this->subtopics->validIdsInTopic($topicId, array_column($subtopicReference, 'id'));

        if ($subtopicId !== null && ! in_array($subtopicId, $validSubtopicIds, true)) {
            throw new InsufficientContextException;
        }

        $result = $this->ai->generateQuiz($context, $difficulty, $questionCount, $subtopicReference);

        $questions = $this->validateQuestions($result->questions, $topicId, $validSubtopicIds, $questionCount);

        return DB::transaction(function () use ($session, $topicId, $subtopicId, $difficulty, $questions) {
            $quiz = $this->quizzes->create([
                'study_session_id' => $session->id,
                'topic_id' => $topicId,
                'subtopic_id' => $subtopicId,
                'difficulty' => $difficulty,
                'status' => 'in_progress',
                'total_questions' => count($questions),
            ]);

            $rows = array_map(
                fn (array $question, int $index) => [
                    'subtopic_id' => $question['subtopic_id'],
                    'question_type' => $question['question_type'],
                    'question_text' => $question['question_text'],
                    'options' => $question['options'],
                    'correct_answer' => $question['correct_answer'],
                    'order_index' => $index,
                ],
                $questions,
                array_keys($questions)
            );

            $this->quizzes->insertQuestions($quiz, $rows);

            return $quiz->refresh();
        });
    }

    public function questions(Quiz $quiz)
    {
        return $this->quizzes->questions($quiz);
    }

    public function answeredCount(Quiz $quiz): int
    {
        return $this->quizzes->answeredCount($quiz);
    }

    /**
     * Whether every question on the quiz now has an answer.
     */
    public function isComplete(Quiz $quiz): bool
    {
        return $quiz->status === 'completed'
            || $this->answeredCount($quiz) >= $quiz->total_questions;
    }

    /**
     * Submit and evaluate one answer; update mastery and (conditionally) complete the quiz.
     *
     * @throws QuizAnswerConflictException
     */
    public function answer(Quiz $quiz, QuizQuestion $question, string $submittedAnswer): array
    {
        if ($quiz->status === 'completed') {
            throw QuizAnswerConflictException::quizAlreadyCompleted();
        }

        if ($this->answers->hasAnswerForQuestion($question->id)) {
            throw QuizAnswerConflictException::questionAlreadyAnswered();
        }

        $evaluation = $this->ai->evaluateAnswer(
            $question->question_text,
            $question->correct_answer,
            $submittedAnswer
        );

        $subtopic = DB::transaction(function () use ($quiz, $question, $submittedAnswer, $evaluation) {
            $this->answers->create([
                'quiz_question_id' => $question->id,
                'submitted_answer' => $submittedAnswer,
                'is_correct' => $evaluation->isCorrect,
                'ai_feedback' => $evaluation->feedback,
                'answered_at' => Carbon::now(),
            ]);

            $subtopic = $this->subtopics->recalculateMastery($question->subtopic_id);

            $answeredCount = $this->quizzes->answeredCount($quiz);

            if ($answeredCount >= $quiz->total_questions) {
                $correctCount = $this->quizzes->questionsWithAnswers($quiz)
                    ->filter(fn (QuizQuestion $q) => $q->answer?->is_correct)
                    ->count();

                $this->quizzes->markCompleted($quiz, $correctCount);
            }

            return $subtopic;
        });

        return [
            'quiz_question_id' => $question->id,
            'submitted_answer' => $submittedAnswer,
            'is_correct' => $evaluation->isCorrect,
            'ai_feedback' => $evaluation->feedback,
            'quiz_status' => $this->isComplete($quiz) ? 'completed' : 'in_progress',
            'subtopic' => [
                'id' => $subtopic->id,
                'mastery_score' => (float) $subtopic->mastery_score,
                'status' => $subtopic->status,
            ],
        ];
    }

    /**
     * Laravel-level business validation of AI-generated questions (§10.2):
     * question count, supported types, and subtopic ownership within $topicId.
     *
     * @param  list<array{question_type: string, question_text: string, options: array|null, correct_answer: string, subtopic_id: int}>  $questions
     * @param  list<int>  $validSubtopicIds
     * @return list<array{question_type: string, question_text: string, options: array|null, correct_answer: string, subtopic_id: int}>
     *
     * @throws InsufficientContextException when the AI references an invalid subtopic
     */
    private function validateQuestions(array $questions, int $topicId, array $validSubtopicIds, int $questionCount): array
    {
        if (count($questions) !== $questionCount) {
            throw new InsufficientContextException;
        }

        if ($validSubtopicIds !== []) {
            $set = array_flip($validSubtopicIds);

            $acceptable = array_filter(
                $questions,
                fn (array $question) => isset($set[$question['subtopic_id']])
            );

            if (count($acceptable) !== $questionCount) {
                throw new InsufficientContextException;
            }
        }

        return $questions;
    }
}
