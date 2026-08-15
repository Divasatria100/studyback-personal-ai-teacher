<?php

namespace Tests\Unit\Services\Processing;

use App\Services\Processing\ChunkSubtopicAssigner;
use Tests\TestCase;

/**
 * Deterministic positional tagging of chunks to AI-identified subtopics
 * (AI Architecture §5) — runs entirely in Laravel, never depends on extra
 * per-chunk AI classification.
 */
class ChunkSubtopicAssignerTest extends TestCase
{
    private ChunkSubtopicAssigner $assigner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assigner = new ChunkSubtopicAssigner;
    }

    public function test_no_subtopics_assigns_null_to_each_chunk(): void
    {
        $this->assertSame([null, null, null], $this->assigner->assign([], 3));
    }

    public function test_zero_chunks_returns_empty_list(): void
    {
        $this->assertSame([], $this->assigner->assign([['topicId' => 1, 'subtopicId' => 5]], 0));
    }

    public function test_single_subtopic_assigns_all_chunks_to_it(): void
    {
        $assignment = $this->assigner->assign([['topicId' => 1, 'subtopicId' => 5]], 4);

        $this->assertSame([5, 5, 5, 5], $assignment);
    }

    public function test_subtopics_are_distributed_across_document(): void
    {
        $subtopics = [
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 6],
            ['topicId' => 2, 'subtopicId' => 7],
        ];

        $assignment = $this->assigner->assign($subtopics, 9);

        $this->assertSame([5, 5, 5, 6, 6, 6, 7, 7, 7], $assignment);
    }

    public function test_uneven_chunk_count_reaches_every_subtopic(): void
    {
        $subtopics = [
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 2, 'subtopicId' => 6],
        ];

        $assignment = $this->assigner->assign($subtopics, 5);

        $this->assertSame([5, 5, 5, 6, 6], $assignment);
        $this->assertEqualsCanonicalizing([5, 6], array_unique($assignment));
    }
}
