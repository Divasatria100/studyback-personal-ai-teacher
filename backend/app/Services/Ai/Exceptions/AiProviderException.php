<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * Raised by ai_service when the configured provider (and any optional fallback)
 * cannot be reached: network errors, timeouts, HTTP 5xx, or empty responses —
 * after the configured retry policy has been exhausted (AI Architecture §13).
 *
 * Maps to HTTP 503 Service Unavailable.
 */
class AiProviderException extends RuntimeException
{
    public static function unreachable(string $message): self
    {
        return new self($message);
    }
}
