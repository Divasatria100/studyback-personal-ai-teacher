<?php

namespace Tests\Unit\Services\Processing;

use App\Services\Processing\Exceptions\PdfExtractionException;
use App\Services\Processing\PdfTextExtractor;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * spatie/pdf-to-text (primary) → smalot/pdfparser (fallback) extraction chain
 * per Tech Stack §4. The primary shells out to a real pdftotext binary, so the
 * fallback path is exercised by injecting a mocked smalot Parser and pointing
 * the pdftotext binary at a path that cannot run.
 */
class PdfTextExtractorTest extends TestCase
{
    private function parserMockReturning(string $text): Parser
    {
        $document = $this->createMock(Document::class);
        $document->method('getText')->willReturn($text);

        $parser = $this->createMock(Parser::class);
        $parser->method('parseFile')->willReturn($document);

        return $parser;
    }

    private function parserMockThrowing(): Parser
    {
        $parser = $this->createMock(Parser::class);
        $parser->method('parseFile')->willThrowException(new \RuntimeException('Cannot parse PDF.'));

        return $parser;
    }

    private function extractorWithUnavailablePrimary(Parser $fallback): PdfTextExtractor
    {
        return new PdfTextExtractor($fallback);
    }

    public function test_primary_failure_falls_back_to_smalot(): void
    {
        config()->set('processing.pdftotext_binary', 'C:\\missing\\pdftotext.exe');

        $extractor = $this->extractorWithUnavailablePrimary(
            $this->parserMockReturning('Extracted by smalot fallback.')
        );

        $this->assertSame('Extracted by smalot fallback.', $extractor->extract(__FILE__));
    }

    public function test_primary_and_fallback_failure_throws_extraction_exception(): void
    {
        config()->set('processing.pdftotext_binary', 'C:\\missing\\pdftotext.exe');

        $extractor = $this->extractorWithUnavailablePrimary($this->parserMockThrowing());

        $this->expectException(PdfExtractionException::class);

        $extractor->extract(__FILE__);
    }

    public function test_empty_fallback_text_throws_extraction_exception(): void
    {
        config()->set('processing.pdftotext_binary', 'C:\\missing\\pdftotext.exe');

        $extractor = $this->extractorWithUnavailablePrimary($this->parserMockReturning('   '));

        $this->expectException(PdfExtractionException::class);

        $extractor->extract(__FILE__);
    }
}
