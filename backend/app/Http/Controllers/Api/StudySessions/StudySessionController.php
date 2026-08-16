<?php

namespace App\Http\Controllers\Api\StudySessions;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudySessions\StoreExplanationRequest;
use App\Http\Requests\StudySessions\StoreQuizRequest;
use App\Http\Requests\StudySessions\StoreStudySessionRequest;
use App\Http\Resources\QuizResource;
use App\Http\Resources\StudySessionResource;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\SubtopicRepositoryInterface;
use App\Repositories\Contracts\TopicRepositoryInterface;
use App\Services\Quizzes\Exceptions\InsufficientContextException;
use App\Services\Quizzes\QuizService;
use App\Services\StudySessions\Exceptions\StudySessionAlreadyCompletedException;
use App\Services\StudySessions\Exceptions\SubtopicNotInMaterialException;
use App\Services\StudySessions\Exceptions\TopicHasSubtopicsException;
use App\Services\StudySessions\Exceptions\TopicNotInMaterialException;
use App\Services\StudySessions\StudySessionService;
use Illuminate\Http\JsonResponse;

/**
 * Study Session module endpoints (API Design §11): creation, retrieval,
 * completion, Teach Me explanations, and quiz generation.
 */
class StudySessionController extends Controller
{
    public function __construct(
        private readonly MaterialRepositoryInterface $materials,
        private readonly TopicRepositoryInterface $topics,
        private readonly SubtopicRepositoryInterface $subtopics,
        private readonly StudySessionService $sessions,
        private readonly QuizService $quizzes,
    ) {}

    public function store(StoreStudySessionRequest $request, int $material): JsonResponse
    {
        $material = $this->materials->findOwnedByUser($request->user()->id, $material);

        abort_if($material === null, 404);

        $data = $request->validated();
        $topicIds = $data['topic_ids'];

        $validTopicIds = $this->topics->existingIdsInMaterial($material->id, $topicIds);

        if (count($validTopicIds) !== count($topicIds)) {
            return response()->json([
                'message' => 'One or more selected topics do not belong to this material.',
                'errors' => ['topic_ids' => ['One or more topic_ids are invalid.']],
            ], 422);
        }

        $session = $this->sessions->create(
            userId: $request->user()->id,
            materialId: $material->id,
            mode: $data['mode'],
            difficulty: $data['difficulty'] ?? null,
            topicIds: $validTopicIds,
        );

        return new JsonResponse(new StudySessionResource($session->load('topics')), 201);
    }

    public function show(int $studySession): JsonResponse
    {
        $session = $this->sessions->findOwnedByUser(auth()->id(), $studySession);

        abort_if($session === null, 404);

        return new JsonResponse(new StudySessionResource($session->load('topics')));
    }

    public function complete(int $studySession): JsonResponse
    {
        $session = $this->sessions->findOwnedByUser(auth()->id(), $studySession);

        abort_if($session === null, 404);

        try {
            $session = $this->sessions->complete($session);
        } catch (StudySessionAlreadyCompletedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new JsonResponse([
            'id' => $session->id,
            'status' => $session->status,
            'ended_at' => $session->ended_at,
        ]);
    }

    public function explanations(StoreExplanationRequest $request, int $studySession): JsonResponse
    {
        $session = $this->sessions->findOwnedByUser(auth()->id(), $studySession);

        abort_if($session === null, 404);

        try {
            $result = $this->sessions->explain(
                session: $session,
                subtopicId: $request->validated('subtopic_id') !== null ? (int) $request->validated('subtopic_id') : null,
                topicId: $request->validated('topic_id') !== null ? (int) $request->validated('topic_id') : null,
                intent: $request->validated('intent'),
                message: $request->validated('message'),
            );
        } catch (SubtopicNotInMaterialException $e) {
            abort(404, $e->getMessage());
        } catch (TopicNotInMaterialException $e) {
            abort(404, $e->getMessage());
        } catch (TopicHasSubtopicsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse($result);
    }

    public function storeQuiz(StoreQuizRequest $request, int $studySession): JsonResponse
    {
        $session = $this->sessions->findOwnedByUser(auth()->id(), $studySession);

        abort_if($session === null, 404);

        $data = $request->validated();
        $topicId = (int) $data['topic_id'];
        $subtopicId = isset($data['subtopic_id']) ? (int) $data['subtopic_id'] : null;
        $difficulty = $data['difficulty'] ?? $session->difficulty ?? 'medium';
        $questionCount = (int) ($data['question_count'] ?? 5);

        $validTopicIds = $this->topics->existingIdsInMaterial($session->material_id, [$topicId]);

        if ($validTopicIds === []) {
            return response()->json([
                'message' => 'The selected topic does not belong to this material.',
                'errors' => ['topic_id' => ['The selected topic is invalid.']],
            ], 422);
        }

        if ($subtopicId !== null && $this->subtopics->findInTopic($topicId, $subtopicId) === null) {
            return response()->json([
                'message' => 'The selected subtopic does not belong to this topic.',
                'errors' => ['subtopic_id' => ['The selected subtopic is invalid.']],
            ], 422);
        }

        try {
            $quiz = $this->quizzes->generate(
                session: $session,
                topicId: $topicId,
                subtopicId: $subtopicId,
                difficulty: $difficulty,
                questionCount: $questionCount,
                subtopicReference: $this->subtopics->allInTopic($topicId),
            );
        } catch (InsufficientContextException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse(
            new QuizResource($quiz->load(['questions.answer', 'questions.subtopic', 'topic'])),
            201
        );
    }
}
