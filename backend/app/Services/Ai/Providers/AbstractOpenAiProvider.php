<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LLMProviderInterface;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base adapter for OpenAI-compatible HTTP providers (OpenRouter, Featherless).
 * Provider-specific details (base URL, API key, model) are injected from the
 * configuration layer, keeping the rest of the application provider-agnostic.
 */
abstract class AbstractOpenAiProvider implements LLMProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected readonly array $config) {}

    public function complete(array $messages, int $maxTokens = 2048, int $timeout = 60): string
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $apiKey = (string) ($this->config['api_key'] ?? '');
        $model = (string) ($this->config['model'] ?? '');

        if ($apiKey === '') {
            throw AiProviderException::unreachable(sprintf('Missing API key for provider "%s".', $this->name()));
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout($timeout)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => $maxTokens,
                ]);
        } catch (ConnectionException $e) {
            throw AiProviderException::unreachable(sprintf('Connection to "%s" failed: %s', $this->name(), $e->getMessage()));
        }

        if ($response->serverError() || $response->clientError()) {
            throw AiProviderException::unreachable(sprintf(
                'Provider "%s" returned HTTP %d.',
                $this->name(),
                $response->status()
            ));
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw AiProviderException::unreachable(sprintf('Provider "%s" returned an empty response.', $this->name()));
        }

        // Detect and reject safety-only / unusable responses (e.g., "User Safety: safe")
        if ($this->isUnusableContent($content)) {
            Log::warning('[AI PROVIDER] Unusable response detected', [
                'provider' => $this->name(),
                'model' => $model,
                'content_preview' => substr(trim($content), 0, 100),
            ]);
            throw AiProviderException::unreachable('Provider returned an unusable response (safety-only).');
        }

        return $content;
    }

    /**
     * Check if the AI response content is unusable (safety-only, etc.).
     * Only exact/normalized matches for known unusable patterns are rejected.
     * Valid content with similar words is preserved.
     */
    protected function isUnusableContent(string $content): bool
    {
        $normalized = strtolower(trim($content));

        // Exact safety-only responses observed from providers
        $unusablePatterns = [
            'user safety: safe',
            'user safety: unsafe',
        ];

        return in_array($normalized, $unusablePatterns, true);
    }

    protected function name(): string
    {
        return static::class;
    }
}
