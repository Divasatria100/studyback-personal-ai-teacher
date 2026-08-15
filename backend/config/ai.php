<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active AI Provider & Model
    |--------------------------------------------------------------------------
    |
    | The AI integration is provider-agnostic. Provider and model are selected
    | through environment configuration (Tech Stack Specification §7.3), never
    | hard-coded into application/business logic.
    |
    | Default provider: OpenRouter — default route: openrouter/free
    | Optional provider: Featherless.ai (used when configured)
    | Dev/test provider:  Mock AI Provider (never calls an external API)
    |
    */

    'provider' => env('AI_PROVIDER', 'openrouter'),
    'model' => env('AI_MODEL', 'openrouter/free'),

    /*
    |--------------------------------------------------------------------------
    | Optional fallback provider
    |--------------------------------------------------------------------------
    |
    | When the active provider fails (after the configured retry policy), the
    | orchestrator may try an optional fallback provider (e.g. Featherless.ai)
    | if it is configured. Fallback behavior is configurable, never hard-coded.
    |
    */

    'fallback_provider' => env('AI_FALLBACK_PROVIDER'),
    'fallback_model' => env('AI_FALLBACK_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Request & retry settings
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('AI_REQUEST_TIMEOUT', 60),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
    'retry_attempts' => (int) env('AI_RETRY_ATTEMPTS', 1),
    'retry_delay_ms' => (int) env('AI_RETRY_DELAY_MS', 250),

    /*
    |--------------------------------------------------------------------------
    | Provider configuration
    |--------------------------------------------------------------------------
    |
    | Provider-specific base URLs, credentials and models are isolated inside
    | this config layer — application modules never depend on any provider.
    |
    */

    'providers' => [
        'openrouter' => [
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('AI_MODEL', 'openrouter/free'),
        ],

        'featherless' => [
            'base_url' => env('FEATHERLESS_BASE_URL', 'https://api.featherless.ai/v1'),
            'api_key' => env('FEATHERLESS_API_KEY'),
            'model' => env('FEATHERLESS_MODEL', env('AI_FALLBACK_MODEL')),
        ],

        'mock' => [
            // Optional deterministic overrides used by the dev/test provider.
            'failure' => env('MOCK_AI_FAILURE'),
            'override_topics' => env('MOCK_AI_TOPICS'),
            'override_questions' => env('MOCK_AI_QUESTIONS'),
            'override_evaluation' => env('MOCK_AI_EVALUATION'),
            'override_explanation' => env('MOCK_AI_EXPLANATION'),
        ],
    ],
];
