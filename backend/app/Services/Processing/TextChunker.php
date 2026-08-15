<?php

namespace App\Services\Processing;

/**
 * Deterministic fixed-length chunking (~1,000 characters, ~200 character
 * overlap, no heading detection) per Database Design §10 and AI Architecture §5.
 * Runs in memory; chunks are persisted only after AI topic identification.
 */
final class TextChunker
{
    public const CHUNK_SIZE = 1000;

    public const OVERLAP = 200;

    /**
     * @return list<string> chunks ordered by their position in the source text
     */
    public function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= self::CHUNK_SIZE) {
            return [$text];
        }

        $chunks = [];
        $length = mb_strlen($text);
        $step = self::CHUNK_SIZE - self::OVERLAP;
        $index = 0;

        while ($index < $length) {
            $chunkEnd = min($index + self::CHUNK_SIZE, $length);

            $chunk = mb_substr($text, $index, $chunkEnd - $index);
            $chunks[] = trim($chunk);

            if ($chunkEnd >= $length) {
                break;
            }

            $index = $chunkEnd - self::OVERLAP;
        }

        return $chunks;
    }
}
