<?php

namespace App\Services\Processing;

use App\Services\Processing\Exceptions\PdfExtractionException;
use Spatie\PdfToText\Exceptions\CouldNotExtractText;
use Spatie\PdfToText\Pdf;

/**
 * Extracts raw text from an uploaded PDF using spatie/pdf-to-text (poppler's
 * pdftotext binary, provisioned in the Docker image). Deterministic library
 * step in the processing pipeline — never AI (AI Architecture §5).
 */
class PdfTextExtractor
{
    public function extract(string $path): string
    {
        $binary = config('processing.pdftotext_binary');

        try {
            $text = Pdf::getText($path, $binary !== '' ? $binary : null);
        } catch (CouldNotExtractText $e) {
            throw new PdfExtractionException($e->getMessage());
        }

        if (trim($text) === '') {
            throw new PdfExtractionException('The uploaded file produced no readable text.');
        }

        return $text;
    }
}
