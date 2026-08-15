<?php

namespace App\Repositories\Contracts;

use App\Models\Subtopic;
use Illuminate\Database\Eloquent\Collection;

interface SubtopicRepositoryInterface
{
    public function findById(int $id): ?Subtopic;

    public function findInTopic(int $topicId, int $subtopicId): ?Subtopic;

    /**
     * Bulk-insert subtopics and return the persisted models.
     *
     * @param  list<array{topic_id: int, name: string, description?: string|null, order_index?: int}>  $subtopicsData
     * @return Collection<int, Subtopic>
     */
    public function bulkCreate(array $subtopicsData): Collection;

    /**
     * Return the subset of the given subtopic ids that actually belong to the topic.
     *
     * @param  list<int>  $subtopicIds
     * @return list<int>
     */
    public function validIdsInTopic(int $topicId, array $subtopicIds): array;

    /**
     * Recompute a subtopic's mastery as the cumulative average of every answer ever
     * recorded for it (Database Design §8), derive the learning status from the fixed
     * thresholds, and persist both. Returns the updated subtopic.
     */
    public function recalculateMastery(int $subtopicId): Subtopic;
}