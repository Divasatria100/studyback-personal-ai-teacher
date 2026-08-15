<?php

namespace App\Services\Ai\Providers;

/**
 * Featherless.ai adapter — optional hackathon provider, used only when
 * configured (Tech Stack Specification §7.1). Uses the shared
 * OpenAI-compatible transport.
 */
class FeatherlessProvider extends AbstractOpenAiProvider
{
    protected function name(): string
    {
        return 'featherless';
    }
}
