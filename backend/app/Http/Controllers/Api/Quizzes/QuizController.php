<?php

namespace App\Http\Controllers\Api\Quizzes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quizzes\StoreQuizAnswerRequest;
use App\Http\Resources\QuizResource;
use App\Services\Quizzes\Exceptions\QuizAnswerConflictException;
use App\Services\Quizzes\QuizService;
use Illuminate\Http\JsonResponse;

/**
 * Quiz retrieval + answer submission endpoints (API Design §12).
 */
class QuizController extends Controller
{
    public function __construct(private readonly QuizService $quizzes) {}

    public function show(int $quiz): JsonResponse
    {
        $quiz = $this->quizzes->findOwnedByUser(auth()->id(), $quiz);

        abort_if($quiz === null, 404);

        return new JsonResponse(
            new QuizResource($quiz->load(['questions.answer', 'questions.subtopic']))
        );
    }

    public function answer(StoreQuizAnswerRequest $request, int $quiz, int $quizQuestion): JsonResponse
    {
        $quiz = $this->quizzes->findOwnedByUser(auth()->id(), $quiz);

        abort_if($quiz === null, 404);

        $question = $this->quizzes->findQuestionInQuiz($quiz->id, $quizQuestion);

        abort_if($question === null, 404);

        try {
            $result = $this->quizzes->answer($quiz, $question, $request->validated()['submitted_answer']);
        } catch (QuizAnswerConflictException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $quiz->refresh();

        if ($result['quiz_status'] === 'completed') {
            $result['quiz_result'] = [
                'correct_count' => $quiz->correct_count,
                'total_questions' => $quiz->total_questions,
                'score' => (float) $quiz->score,
            ];
        }

        return new JsonResponse($result);
    }
}
