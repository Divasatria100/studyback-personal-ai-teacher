<?php

namespace App\Repositories\Contracts;

interface ChunkRepositoryInterface
{
    /**
     * Bulk-insert chunks.
     *
     * @param  list<array{material_id: int, topic_id: int, subtopic_id?: int|null, content: string, chunk_index: int}>  $chunksData
     */
    public function bulkCreate(array $chunksData): void;

    /**
     * Ordered chunk contents for retrieval, scoped to a material and (optionally)
     * a topic/subtopic — the internal filter-based RAG query (Architecture §8).
     *
     * @return list<string>
     */
    public function retrieveContext(int $materialId, ?int $topicId = null, ?int $subtopicId = null): array;

    /**
     * Whether any chunk exists for the requested material/topic scope.
     */
    public function hasChunks(int $materialId, ?int $topicId = null, ?int $subtopicId = null): bool;
}