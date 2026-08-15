<?php

namespace App\Providers;

use App\Repositories\Contracts\ChunkRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\QuizAnswerRepositoryInterface;
use App\Repositories\Contracts\QuizRepositoryInterface;
use App\Repositories\Contracts\StudySessionRepositoryInterface;
use App\Repositories\Contracts\SubtopicRepositoryInterface;
use App\Repositories\Contracts\TopicRepositoryInterface;
use App\Repositories\Eloquent\EloquentChunkRepository;
use App\Repositories\Eloquent\EloquentMaterialRepository;
use App\Repositories\Eloquent\EloquentQuizAnswerRepository;
use App\Repositories\Eloquent\EloquentQuizRepository;
use App\Repositories\Eloquent\EloquentStudySessionRepository;
use App\Repositories\Eloquent\EloquentSubtopicRepository;
use App\Repositories\Eloquent\EloquentTopicRepository;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Contracts\AiServiceInterface;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\Providers\ProviderFactory;
use App\Services\Ai\StructuredOutputValidator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerAiService();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    private function registerRepositories(): void
    {
        $this->app->singleton(
            MaterialRepositoryInterface::class,
            EloquentMaterialRepository::class
        );

        $this->app->singleton(
            TopicRepositoryInterface::class,
            EloquentTopicRepository::class
        );

        $this->app->singleton(
            SubtopicRepositoryInterface::class,
            EloquentSubtopicRepository::class
        );

        $this->app->singleton(
            ChunkRepositoryInterface::class,
            EloquentChunkRepository::class
        );

        $this->app->singleton(
            StudySessionRepositoryInterface::class,
            EloquentStudySessionRepository::class
        );

        $this->app->singleton(
            QuizRepositoryInterface::class,
            EloquentQuizRepository::class
        );

        $this->app->singleton(
            QuizAnswerRepositoryInterface::class,
            EloquentQuizAnswerRepository::class
        );
    }

    /**
     * Bind the active LLM provider (through the Provider Abstraction) and the
     * ai_service orchestrator. Application modules depend on AiServiceInterface
     * only — never on a specific provider (AI Architecture §3, §11.3).
     */
    private function registerAiService(): void
    {
        $this->app->singleton(AiServiceInterface::class, function (): AiServiceInterface {
            $primary = ProviderFactory::make(
                (string) config('ai.provider'),
                (string) config('ai.model')
            );

            $fallbackName = config('ai.fallback_provider');

            $fallback = $fallbackName
                ? ProviderFactory::make((string) $fallbackName, (string) config('ai.fallback_model'))
                : null;

            return new AiOrchestrator(
                primary: $primary,
                fallback: $fallback,
                promptBuilder: new PromptBuilder,
                validator: new StructuredOutputValidator,
                retryAttempts: (int) config('ai.retry_attempts'),
                retryDelayMs: (int) config('ai.retry_delay_ms'),
                maxTokens: (int) config('ai.max_tokens'),
                timeout: (int) config('ai.timeout'),
            );
        });
    }
}
