<?php

namespace App\Repositories\Eloquent;

use App\Models\Chunk;
use App\Repositories\Contracts\ChunkRepositoryInterface;

class EloquentChunkRepository implements ChunkRepositoryInterface
{
    public function bulkCreate(array $chunksData): void
    {
        Chunk::query()->insert($chunksData);
    }

    public function retrieveContext(int $materialId, ?int $topicId = null, ?int $subtopicId = null): array
    {
        $query = $this->baseScope($materialId, $topicId, $subtopicId);

        return $query
            ->orderBy('chunk_index')
            ->pluck('content')
            ->map(fn ($content) => (string) $content)
            ->all();
    }

    public function hasChunks(int $materialId, ?int $topicId = null, ?int $subtopicId = null): bool
    {
        return $this->baseScope($materialId, $topicId, $subtopicId)->exists();
    }

    private function baseScope(int $materialId, ?int $topicId, ?int $subtopicId)
    {
        if ($topicId === null && $subtopicId === null) {
            return Chunk::query()->where('material_id', $materialId);
        }

        return Chunk::query()
            ->where('material_id', $materialId)
            ->where(function ($query) use ($topicId, $subtopicId) {
                $query->where('topic_id', $topicId);

                if ($subtopicId !== null) {
                    $query->orWhere('subtopic_id', $subtopicId);
                }
            });
    }
}