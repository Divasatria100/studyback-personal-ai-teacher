<?php

namespace App\Services\StudySessions;

use App\Models\StudySession;
use App\Repositories\Contracts\ChunkRepositoryInterface;
use App\Repositories\Contracts\StudySessionRepositoryInterface;
use App\Repositories\Contracts\SubtopicRepositoryInterface;
use App\Services\Ai\Contracts\AiServiceInterface;
use App\Services\StudySessions\Exceptions\StudySessionAlreadyCompletedException;
use App\Services\StudySessions\Exceptions\SubtopicNotInMaterialException;
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
     * Generate a grounded explanation for a topic/subtopic (API Design §14.2).
     *
     * @param  array{int}  $scopes  topic/subtopic scope applied during retrieval
     * @return array{subtopic_id: int, explanation: string}
     */
    public function explain(StudySession $session, int $subtopicId, string $intent, ?string $message): array
    {
        $subtopic = $this->subtopics->findById($subtopicId);

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
            'explanation' => $this->ai->explain($context, $intent, $message),
        ];
    }
}
