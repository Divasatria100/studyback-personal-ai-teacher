<?php

namespace App\Services\Processing;

/**
 * Deterministic mapping of chunks to the subtopics identified by AI. The AI only
 * returns a topic/subtopic tree (no per-chunk classification), so the Processing
 * Module assigns each chunk positionally across the identified subtopics in
 * document order (AI Architecture §5). This keeps every identified subtopic
 * retrievable while remaining fully deterministic — it never depends on AI output
 * beyond the structural tree itself.
 */
final class ChunkSubtopicAssigner
{
    /**
     * Assign chunks to subtopics.
     *
     * @param  list<array{topicId: int, subtopicId: int}>  $subtopics  flattened subtopics in order
     * @param  int  $chunkCount  number of chunks to assign
     * @return list<int|null> one subtopic_id per chunk (in order), null when there are no subtopics
     */
    public function assign(array $subtopics, int $chunkCount): array
    {
        if ($subtopics === [] || $chunkCount === 0) {
            return array_fill(0, $chunkCount, null);
        }

        $ids = array_column($subtopics, 'subtopicId');
        $total = count($ids);

        return array_map(
            fn (int $chunkIndex) => $ids[(int) floor($chunkIndex * $total / $chunkCount)],
            range(0, $chunkCount - 1)
        );
    }
}
