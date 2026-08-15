<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Exceptions\AiProviderException;

/**
 * Uniform interface every LLM provider adapter implements. It hides
 * provider-specific details (base URL, auth header, request/response format)
 * from the rest of ai_service and from application modules (AI Architecture §3).
 */
interface LLMProviderInterface
{
    /**
     * Send an OpenAI-compatible chat completion request and return the raw
     * assistant message content.
     *
     * @param  list<array{role: string, content: string}>  $messages
     *
     * @throws AiProviderException when the provider
     *                             is unreachable, times out, or returns an empty response.
     */
    public function complete(array $messages, int $maxTokens = 2048, int $timeout = 60): string;
}
