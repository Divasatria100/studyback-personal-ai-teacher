<?php

namespace App\Services\Processing;

/**
 * In-memory cleaning of extracted text before chunking (AI Architecture §5).
 * Purely deterministic string normalization — nothing is persisted.
 */
final class TextCleaner
{
    public function clean(string $text): string
    {
        return $this->normalizeWhitespace($text);
    }

    /**
     * Collapse page-break artifacts, multiple blank lines and stray unicode
     * whitespace into a single readable stream.
     */
    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/\x{00ad}/u', '', $text) ?? $text;

        $text = preg_replace('/[\p{Z}\t\x0b\x0c]+/u', ' ', $text) ?? $text;

        $text = preg_replace('/\R/', "\n", $text) ?? $text;

        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;

        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
