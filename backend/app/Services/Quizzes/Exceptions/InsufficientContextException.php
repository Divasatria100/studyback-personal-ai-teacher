<?php

namespace App\Services\Quizzes\Exceptions;

use RuntimeException;

/**
 * Raised when retrieval finds no chunks for the requested topic/subtopic scope.
 * Laravel fails before ever calling the LLM (Architecture §13).
 * Maps to HTTP 422 Unprocessable Entity (API Design §12).
 */
final class InsufficientContextException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No material context was found for the requested topic scope.');
    }
}
