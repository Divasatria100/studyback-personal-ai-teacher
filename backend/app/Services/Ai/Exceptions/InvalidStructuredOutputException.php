<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * Raised by ai_service when the provider returned content that is not valid JSON
 * matching the capability's schema (AI Architecture §10) after the configured
 * regeneration retry policy and optional fallback have been exhausted.
 *
 * For material processing this is converted by the Processing module into a
 * failed material (HTTP 422). For other capabilities it surfaces as HTTP 503.
 */
class InvalidStructuredOutputException extends RuntimeException
{
    public static function forCapability(string $capability, string $reason = ''): self
    {
        $message = sprintf('AI returned invalid structured output for "%s".', $capability).($reason !== '' ? ' '.$reason : '');

        return new self($message);
    }
}
