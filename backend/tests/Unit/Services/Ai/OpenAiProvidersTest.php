<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Providers\FeatherlessProvider;
use App\Services\Ai\Providers\OpenRouterProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HTTP transport of the OpenAI-compatible provider adapters (AI Architecture
 * §3, §11). Each adapter isolates base URL, auth header and request shape —
 * nothing provider-specific leaks into ai_service core or application modules.
 */
class OpenAiProvidersTest extends TestCase
{
    private function openRouter(array $overrides = []): OpenRouterProvider
    {
        return new OpenRouterProvider($overrides + [
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'sk-or',
            'model' => 'openrouter/free',
        ]);
    }

    private function featherless(array $overrides = []): FeatherlessProvider
    {
        return new FeatherlessProvider($overrides + [
            'base_url' => 'https://api.featherless.ai/v1',
            'api_key' => 'sk-fl',
            'model' => 'nemotron-3-nano',
        ]);
    }

    public function test_sends_chat_completions_request_to_openrouter(): void
    {
        Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'response body']]],
        ])]);

        $content = $this->openRouter()->complete(
            ['role' => 'user', 'content' => 'Hello'],
            1024,
            30
        );

        $this->assertSame('response body', $content);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer sk-or')
                && $request->data()['model'] === 'openrouter/free'
                && $request->data()['max_tokens'] === 1024
                && $request->data()['messages'] === ['role' => 'user', 'content' => 'Hello'];
        });
    }

    public function test_sends_request_to_featherless_provider(): void
    {
        Http::fake(['https://api.featherless.ai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'fl response']]],
        ])]);

        $content = $this->featherless()->complete([['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('fl response', $content);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.featherless.ai/v1/chat/completions'
            && $request->data()['model'] === 'nemotron-3-nano');
    }

    public function test_applies_configured_timeout(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

        $this->openRouter()->complete([], 2048, 12);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions';
        });
    }

    public function test_missing_api_key_raises_provider_exception(): void
    {
        $provider = $this->openRouter(['api_key' => '']);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('Missing API key');

        $provider->complete([]);
    }

    public function test_connection_failure_raises_provider_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('Connection to "openrouter" failed: timed out');

        $this->openRouter()->complete([]);
    }

    public function test_http_server_error_raises_provider_exception(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->expectException(AiProviderException::class);

        $this->openRouter()->complete([]);
    }

    public function test_http_client_error_raises_provider_exception(): void
    {
        Http::fake(['*' => Http::response('', 401)]);

        $this->expectException(AiProviderException::class);

        $this->openRouter()->complete([]);
    }

    public function test_empty_content_raises_provider_exception(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '  ']]]])]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('returned an empty response');

        $this->openRouter()->complete([]);
    }

    public function test_missing_content_field_raises_provider_exception(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => []]]])]);

        $this->expectException(AiProviderException::class);

        $this->openRouter()->complete([]);
    }
}
