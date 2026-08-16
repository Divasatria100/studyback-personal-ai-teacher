<?php

namespace App\Services\Processing;

/**
 * Deterministic mapping of chunks to the learning targets identified by AI. The
 * AI only returns a topic/subtopic tree (no per-chunk classification), so the
 * Processing Module assigns each chunk positionally across the identified
 * learning targets in document order (AI Architecture §5). A learning target is
 * either a subtopic (subtopicId set) or a topic-only topic (subtopicId null).
 * This keeps every identified target retrievable while remaining fully
 * deterministic — it never depends on AI output beyond the structural tree.
 */
final class ChunkSubtopicAssigner
{
    /**
     * Assign chunks to learning targets.
     *
     * @param  list<array{topicId: int, subtopicId: int|null}>  $targets  flattened learning targets in order
     * @param  int  $chunkCount  number of chunks to assign
     * @return list<array{topicId: int, subtopicId: int|null}> one target per chunk (in order)
     */
    public function assign(array $targets, int $chunkCount): array
    {
        if ($chunkCount === 0) {
            return [];
        }

        if ($targets === []) {
            throw new \InvalidArgumentException('At least one learning target is required to assign chunks.');
        }

        $total = count($targets);

        return array_map(
            fn (int $chunkIndex) => $targets[(int) floor($chunkIndex * $total / $chunkCount)],
            range(0, $chunkCount - 1)
        );
    }
}