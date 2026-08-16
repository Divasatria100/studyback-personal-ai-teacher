<?php

namespace App\Repositories\Contracts;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Collection;

interface TopicRepositoryInterface
{
    public function findById(int $id): ?Topic;

    public function findInMaterial(int $materialId, int $topicId): ?Topic;

    /**
     * Return all topics of a material including their subtopics, ordered by order_index.
     *
     * @return Collection<int, Topic>
     */
    public function treeForMaterial(int $materialId): Collection;

    /**
     * Bulk-insert topics for a material and return the persisted models.
     *
     * @param  list<array{name: string, description?: string|null, order_index?: int}>  $topicsData
     * @return Collection<int, Topic>
     */
    public function bulkCreateForMaterial(int $materialId, array $topicsData): Collection;

    /**
     * @param  list<int>  $topicIds
     * @return list<int>
     */
    public function existingIdsInMaterial(int $materialId, array $topicIds): array;

    /**
     * Recompute a topic-only target's mastery as the cumulative average of every
     * answer ever recorded for it (topic-scoped quiz questions with a NULL
     * subtopic_id), derive the learning status from the fixed thresholds, and
     * persist both. Returns the updated topic.
     */
    public function recalculateMastery(int $topicId): Topic;
}
