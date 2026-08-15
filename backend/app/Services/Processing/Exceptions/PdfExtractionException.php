<?php

namespace App\Services\Processing\Exceptions;

use RuntimeException;

/**
 * Raised when the uploaded PDF cannot be parsed or yields no readable text.
 * Maps to a processing-pipeline failure (material status = 'failed').
 */
final class PdfExtractionException extends RuntimeException {}
