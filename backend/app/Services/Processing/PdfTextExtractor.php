<?php

namespace App\Services\Processing;

use App\Services\Processing\Exceptions\PdfExtractionException;
use Smalot\PdfParser\Parser;
use Spatie\PdfToText\Pdf;

/**
 * Extracts raw text from an uploaded PDF. Primary extractor is spatie/pdf-to-text
 * (poppler's pdftotext binary, provisioned in the Docker image); when it fails
 * (missing/unusable binary, unreadable or empty extraction) the pipeline falls
 * back to the pure-PHP smalot/pdfparser (Tech Stack §4). If every extractor
 * fails the extraction is treated as failed so the material is never marked
 * ready. Deterministic library step — never AI (AI Architecture §5).
 */
class PdfTextExtractor
{
    public function __construct(private readonly ?Parser $fallbackParser = null) {}

    public function extract(string $path): string
    {
        $text = $this->extractWithSpatie($path);

        if ($text === null) {
            $text = $this->extractWithSmalot($path);
        }

        if ($text === null || trim($text) === '') {
            throw new PdfExtractionException('The uploaded file produced no readable text.');
        }

        return $text;
    }

    private function extractWithSpatie(string $path): ?string
    {
        $binary = config('processing.pdftotext_binary');

        try {
            $text = Pdf::getText($path, $binary !== '' ? $binary : null);

            return trim($text) === '' ? null : $text;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractWithSmalot(string $path): ?string
    {
        try {
            $parser = $this->fallbackParser ?? new Parser;

            return trim($parser->parseFile($path)->getText());
        } catch (\Throwable) {
            return null;
        }
    }
}
