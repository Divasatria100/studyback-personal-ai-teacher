<?php

namespace Tests\Unit\Services\Processing;

use App\Services\Processing\ChunkSubtopicAssigner;
use Tests\TestCase;

/**
 * Deterministic positional tagging of chunks to AI-identified learning targets
 * (AI Architecture §5) — runs entirely in Laravel, never depends on extra
 * per-chunk AI classification. A target is a subtopic (subtopicId set) or a
 * topic-only topic (subtopicId null).
 */
class ChunkSubtopicAssignerTest extends TestCase
{
    private ChunkSubtopicAssigner $assigner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assigner = new ChunkSubtopicAssigner;
    }

    public function test_empty_targets_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->assigner->assign([], 3);
    }

    public function test_zero_chunks_returns_empty_list(): void
    {
        $this->assertSame([], $this->assigner->assign([['topicId' => 1, 'subtopicId' => 5]], 0));
    }

    public function test_single_subtopic_assigns_all_chunks_to_it(): void
    {
        $assignment = $this->assigner->assign([['topicId' => 1, 'subtopicId' => 5]], 4);

        $this->assertSame([
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 5],
        ], $assignment);
    }

    public function test_topic_only_target_assigns_all_chunks_with_null_subtopic(): void
    {
        $assignment = $this->assigner->assign([['topicId' => 2, 'subtopicId' => null]], 3);

        $this->assertSame([
            ['topicId' => 2, 'subtopicId' => null],
            ['topicId' => 2, 'subtopicId' => null],
            ['topicId' => 2, 'subtopicId' => null],
        ], $assignment);
    }

    public function test_targets_are_distributed_across_document(): void
    {
        $targets = [
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 6],
            ['topicId' => 2, 'subtopicId' => null],
        ];

        $assignment = $this->assigner->assign($targets, 9);

        $this->assertSame([
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 6],
            ['topicId' => 1, 'subtopicId' => 6],
            ['topicId' => 1, 'subtopicId' => 6],
            ['topicId' => 2, 'subtopicId' => null],
            ['topicId' => 2, 'subtopicId' => null],
            ['topicId' => 2, 'subtopicId' => null],
        ], $assignment);
    }

    public function test_uneven_chunk_count_reaches_every_target(): void
    {
        $targets = [
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 2, 'subtopicId' => null],
        ];

        $assignment = $this->assigner->assign($targets, 5);

        $this->assertSame([
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 1, 'subtopicId' => 5],
            ['topicId' => 2, 'subtopicId' => null],
            ['topicId' => 2, 'subtopicId' => null],
        ], $assignment);
    }
}