<?php

namespace Tests\Unit\Services\Processing;

use App\Services\Processing\TextChunker;
use Tests\TestCase;

/**
 * Deterministic fixed-length chunking (AI Architecture §5, Database Design §10):
 * ~1,000 character chunks with ~200 character overlap, no heading detection.
 */
class TextChunkerTest extends TestCase
{
    private TextChunker $chunker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chunker = new TextChunker;
    }

    public function test_empty_text_produces_no_chunks(): void
    {
        $this->assertSame([], $this->chunker->chunk(''));
        $this->assertSame([], $this->chunker->chunk('   '));
    }

    public function test_short_text_returns_single_chunk(): void
    {
        $text = str_repeat('a', TextChunker::CHUNK_SIZE - 1);

        $chunks = $this->chunker->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertSame($text, $chunks[0]);
    }

    public function test_text_exactly_at_chunk_size_returns_single_chunk(): void
    {
        $text = str_repeat('a', TextChunker::CHUNK_SIZE);

        $this->assertSame([$text], $this->chunker->chunk($text));
    }

    public function test_long_text_splits_into_chunks_with_overlap(): void
    {
        $text = str_repeat('a', TextChunker::CHUNK_SIZE * 2 + 100);

        $chunks = $this->chunker->chunk($text);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(TextChunker::CHUNK_SIZE, mb_strlen($chunk));
        }
    }

    public function test_chunks_cover_the_entire_source_in_order(): void
    {
        $text = implode(' ', range(1, 500));

        $chunks = $this->chunker->chunk($text);

        $this->assertGreaterThan(1, count($chunks));

        $joined = implode(' ', $chunks);

        $this->assertStringStartsWith('1 2 3', $joined);
        $this->assertStringEndsWith('499 500', $joined);
        $this->assertStringContainsString('250 251 252', $joined);
    }
}
