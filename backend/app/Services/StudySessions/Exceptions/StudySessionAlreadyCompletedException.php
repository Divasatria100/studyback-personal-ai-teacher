<?php

namespace App\Services\StudySessions\Exceptions;

use RuntimeException;

/**
 * Raised when completing an already-completed study session.
 * Maps to HTTP 409 Conflict (API Design §11).
 */
final class StudySessionAlreadyCompletedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Study session is already completed.');
    }
}
