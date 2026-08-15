<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LLMProviderInterface;
use InvalidArgumentException;

/**
 * Resolves a provider adapter from its configured name. This is the only place
 * in the application that maps provider names to concrete classes. Application
 * modules never depend on a specific provider.
 */
final class ProviderFactory
{
    public static function make(?string $providerName, ?string $model = null): LLMProviderInterface
    {
        $providers = (array) config('ai.providers');

        $config = $providers[$providerName] ?? null;

        if ($config === null) {
            throw new InvalidArgumentException(sprintf('Unknown AI provider "%s".', $providerName));
        }

        if (is_array($config) && $model !== null) {
            $config = [...$config, 'model' => $model];
        }

        return match ($providerName) {
            'openrouter' => new OpenRouterProvider((array) $config),
            'featherless' => new FeatherlessProvider((array) $config),
            'mock' => new MockAiProvider((array) $config),
            default => throw new InvalidArgumentException(sprintf('Unknown AI provider "%s".', $providerName)),
        };
    }
}
