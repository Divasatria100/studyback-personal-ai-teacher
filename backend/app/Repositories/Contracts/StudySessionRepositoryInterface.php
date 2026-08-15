<?php

namespace App\Repositories\Contracts;

use App\Models\StudySession;

interface StudySessionRepositoryInterface
{
    public function create(array $attributes): StudySession;

    public function findById(int $id): ?StudySession;

    public function findOwnedByUser(int $userId, int $studySessionId): ?StudySession;

    /**
     * Attach the selected topics to a study session via the pivot table.
     *
     * @param  list<int>  $topicIds
     */
    public function syncTopics(StudySession $studySession, array $topicIds): void;

    /**
     * @return list<int>
     */
    public function topicIds(StudySession $studySession): array;

    public function setCompleted(StudySession $studySession): StudySession;
}