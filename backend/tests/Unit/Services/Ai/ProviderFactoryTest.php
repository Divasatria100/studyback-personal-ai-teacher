<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\Providers\FeatherlessProvider;
use App\Services\Ai\Providers\MockAiProvider;
use App\Services\Ai\Providers\OpenRouterProvider;
use App\Services\Ai\Providers\ProviderFactory;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Provider selection (AI Architecture §3, §11): ProviderFactory maps a
 * configured name to its adapter and stays the only place that does so.
 * Application modules never depend on a concrete provider.
 */
class ProviderFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.providers', [
            'openrouter' => [
                'base_url' => 'https://openrouter.ai/api/v1',
                'api_key' => 'sk-test-key',
                'model' => 'openrouter/free',
            ],
            'featherless' => [
                'base_url' => 'https://api.featherless.ai/v1',
                'api_key' => 'sk-featherless',
                'model' => 'featherless-model',
            ],
            'mock' => [],
        ]);
    }

    public function test_resolves_openrouter_default_provider(): void
    {
        $this->assertInstanceOf(OpenRouterProvider::class, ProviderFactory::make('openrouter', 'openrouter/free'));
    }

    public function test_resolves_optional_featherless_provider(): void
    {
        $this->assertInstanceOf(FeatherlessProvider::class, ProviderFactory::make('featherless'));
    }

    public function test_resolves_mock_dev_test_provider(): void
    {
        $this->assertInstanceOf(MockAiProvider::class, ProviderFactory::make('mock'));
    }

    public function test_rejects_unknown_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown AI provider "acme".');

        ProviderFactory::make('acme');
    }

    public function test_rejects_null_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProviderFactory::make(null);
    }
}
