<?php

namespace App\Repositories\Eloquent;

use App\Models\StudySession;
use App\Repositories\Contracts\StudySessionRepositoryInterface;
use Illuminate\Support\Carbon;

class EloquentStudySessionRepository implements StudySessionRepositoryInterface
{
    public function create(array $attributes): StudySession
    {
        return StudySession::query()->create($attributes);
    }

    public function findById(int $id): ?StudySession
    {
        return StudySession::query()->find($id);
    }

    public function findOwnedByUser(int $userId, int $studySessionId): ?StudySession
    {
        return StudySession::query()
            ->where('user_id', $userId)
            ->find($studySessionId);
    }

    public function syncTopics(StudySession $studySession, array $topicIds): void
    {
        $now = Carbon::now();

        $pivotRows = [];

        foreach ($topicIds as $topicId) {
            $pivotRows[$topicId] = ['created_at' => $now];
        }

        $studySession->topics()->sync($pivotRows);
    }

    public function topicIds(StudySession $studySession): array
    {
        return $studySession->topics()
            ->pluck('topics.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function setCompleted(StudySession $studySession): StudySession
    {
        $studySession->forceFill([
            'status' => 'completed',
            'ended_at' => Carbon::now(),
        ])->save();

        return $studySession->refresh();
    }
}
