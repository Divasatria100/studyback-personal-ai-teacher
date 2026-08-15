<?php

namespace App\Services\Ai\Providers;

/**
 * OpenRouter adapter — the default provider, using the `openrouter/free`
 * route by default (Tech Stack Specification §7.1). Uses the shared
 * OpenAI-compatible transport.
 */
class OpenRouterProvider extends AbstractOpenAiProvider
{
    protected function name(): string
    {
        return 'openrouter';
    }
}
