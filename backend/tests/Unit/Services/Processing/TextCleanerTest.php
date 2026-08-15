<?php

namespace Tests\Unit\Services\Processing;

use App\Services\Processing\TextCleaner;
use Tests\TestCase;

/**
 * Deterministic text normalization before chunking (AI Architecture §5).
 */
class TextCleanerTest extends TestCase
{
    private TextCleaner $cleaner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleaner = new TextCleaner;
    }

    public function test_removes_soft_hyphen_artifacts(): void
    {
        $this->assertSame('polymorphism', $this->cleaner->clean("poly\xc2\xadmorphism"));
    }

    public function test_collapses_unicode_whitespace(): void
    {
        $input = "hello\tworld\x0cnext\x0bend";
        $this->assertSame('hello world next end', $this->cleaner->clean($input));
    }

    public function test_normalizes_newlines(): void
    {
        $this->assertSame("line one\nline two", $this->cleaner->clean("line one\r\nline two"));
    }

    public function test_trims_trailing_spaces_on_lines(): void
    {
        $this->assertSame("line one\nline two", $this->cleaner->clean("line one   \nline two\t"));
    }

    public function test_collapses_three_or_more_blank_lines_to_one_blank_line(): void
    {
        $this->assertSame("a\n\nb", $this->cleaner->clean("a\n\n\n\n\nb"));
    }

    public function test_trims_outer_whitespace(): void
    {
        $this->assertSame('clean text', $this->cleaner->clean("  \nclean text\n  "));
    }
}
