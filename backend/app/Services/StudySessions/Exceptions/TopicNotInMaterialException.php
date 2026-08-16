<?php

namespace App\Services\StudySessions\Exceptions;

use RuntimeException;

/**
 * Raised when the requested topic does not belong to the session's material.
 * Maps to HTTP 404 Not Found (API Design §14.2).
 */
final class TopicNotInMaterialException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The requested topic does not belong to this material.');
    }
}