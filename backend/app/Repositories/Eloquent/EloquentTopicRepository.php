<?php

namespace App\Repositories\Eloquent;

use App\Models\Topic;
use App\Repositories\Contracts\TopicRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentTopicRepository implements TopicRepositoryInterface
{
    public function findById(int $id): ?Topic
    {
        return Topic::query()->find($id);
    }

    public function findInMaterial(int $materialId, int $topicId): ?Topic
    {
        return Topic::query()
            ->where('material_id', $materialId)
            ->find($topicId);
    }

    public function treeForMaterial(int $materialId): Collection
    {
        return Topic::query()
            ->with(['subtopics' => fn ($q) => $q->orderBy('order_index')])
            ->where('material_id', $materialId)
            ->orderBy('order_index')
            ->get();
    }

    public function bulkCreateForMaterial(int $materialId, array $topicsData): Collection
    {
        $topics = new Collection;

        foreach ($topicsData as $data) {
            $topics->push(Topic::query()->create([
                'material_id' => $materialId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'order_index' => $data['order_index'] ?? 0,
            ]));
        }

        return $topics;
    }

    public function existingIdsInMaterial(int $materialId, array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        return Topic::query()
            ->where('material_id', $materialId)
            ->whereIn('id', $topicIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
