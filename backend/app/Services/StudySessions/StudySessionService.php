<?php

namespace App\Services\StudySessions;

use App\Models\StudySession;
use App\Repositories\Contracts\ChunkRepositoryInterface;
use App\Repositories\Contracts\StudySessionRepositoryInterface;
use App\Repositories\Contracts\SubtopicRepositoryInterface;
use App\Repositories\Contracts\TopicRepositoryInterface;
use App\Services\Ai\Contracts\AiServiceInterface;
use App\Services\StudySessions\Exceptions\StudySessionAlreadyCompletedException;
use App\Services\StudySessions\Exceptions\SubtopicNotInMaterialException;
use App\Services\StudySessions\Exceptions\TopicHasSubtopicsException;
use App\Services\StudySessions\Exceptions\TopicNotInMaterialException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Study Session application module (API Design §11): creation, retrieval,
 * completion, and Teach Me / Review explanation generation. Owns
 * study_sessions.state transitions; explanations never mutate Learning State.
 */
final class StudySessionService
{
    public function __construct(
        private readonly StudySessionRepositoryInterface $studySessions,
        private readonly SubtopicRepositoryInterface $subtopics,
        private readonly TopicRepositoryInterface $topics,
        private readonly ChunkRepositoryInterface $chunks,
        private readonly AiServiceInterface $ai,
    ) {}

    /**
     * Create an active session and attach the selected topics in one transaction.
     *
     * @param  list<int>  $topicIds  validated to belong to $materialId before calling
     */
    public function create(int $userId, int $materialId, string $mode, ?string $difficulty, array $topicIds): StudySession
    {
        return DB::transaction(function () use ($userId, $materialId, $mode, $difficulty, $topicIds) {
            $session = $this->studySessions->create([
                'user_id' => $userId,
                'material_id' => $materialId,
                'mode' => $mode,
                'difficulty' => $difficulty,
                'status' => 'active',
                'started_at' => Carbon::now(),
            ]);

            $this->studySessions->syncTopics($session, $topicIds);

            return $session->refresh();
        });
    }

    public function findOwnedByUser(int $userId, int $studySessionId): ?StudySession
    {
        return $this->studySessions->findOwnedByUser($userId, $studySessionId);
    }

    /**
     * @return list<int>
     */
    public function topicIds(StudySession $session): array
    {
        return $this->studySessions->topicIds($session);
    }

    /**
     * Mark a session completed. Idempotent guard: already-completed sessions
     * reject the completion with StudySessionAlreadyCompletedException.
     */
    public function complete(StudySession $session): StudySession
    {
        if ($session->status === 'completed') {
            throw new StudySessionAlreadyCompletedException;
        }

        return $this->studySessions->setCompleted($session);
    }

    /**
     * Generate a grounded explanation for a learning target (API Design §14.2).
     * The target is either a subtopic (subtopicId) or a topic without subtopics
     * (topicId). Topics that own subtopics are not valid topic-only targets.
     *
     * @return array{subtopic_id?: int, topic_id: int, explanation: string}
     */
    public function explain(StudySession $session, ?int $subtopicId, ?int $topicId, string $intent, ?string $message): array
    {
        if ($subtopicId !== null) {
            $subtopic = $this->subtopics->findBelongsToMaterial($session->material_id, $subtopicId);

            if ($subtopic === null) {
                throw new SubtopicNotInMaterialException;
            }

            $context = $this->chunks->retrieveContext(
                $session->material_id,
                $subtopic->topic_id,
                $subtopicId
            );

            return [
                'subtopic_id' => $subtopicId,
                'topic_id' => $subtopic->topic_id,
                'explanation' => $this->ai->explain($context, $intent, $message),
            ];
        }

        $topic = $this->topics->findInMaterial($session->material_id, $topicId);

        if ($topic === null) {
            throw new TopicNotInMaterialException;
        }

        if ($topic->subtopics()->exists()) {
            throw new TopicHasSubtopicsException;
        }

        $context = $this->chunks->retrieveContext($session->material_id, $topicId, null);

        return [
            'topic_id' => $topicId,
            'explanation' => $this->ai->explain($context, $intent, $message),
        ];
    }
}
