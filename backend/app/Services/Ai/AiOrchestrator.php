<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiServiceInterface;
use App\Services\Ai\Contracts\LLMProviderInterface;
use App\Services\Ai\Dtos\AnswerEvaluationResult;
use App\Services\Ai\Dtos\QuizGenerationResult;
use App\Services\Ai\Dtos\TopicIdentificationResult;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\InvalidStructuredOutputException;
use Throwable;

/**
 * ai_service — the thin, stateless, in-process AI integration boundary of
 * Studyback (AI Architecture §3, §11). It is the only component allowed to talk
 * to an external LLM provider (through the LLM Provider Abstraction), applies
 * the configured retry policy and optional provider fallback, and validates
 * structured output before returning clean results to application modules.
 *
 * It never writes to the database, keeps no state between requests, and is never
 * exposed as an HTTP endpoint.
 */
final class AiOrchestrator implements AiServiceInterface
{
    /**
     * @param  int  $retryAttempts  retries performed per provider after the initial attempt
     * @param  int  $retryDelayMs  delay between retries (ms)
     */
    public function __construct(
        private readonly LLMProviderInterface $primary,
        private readonly ?LLMProviderInterface $fallback,
        private readonly PromptBuilder $promptBuilder,
        private readonly StructuredOutputValidator $validator,
        private readonly int $retryAttempts = 1,
        private readonly int $retryDelayMs = 250,
        private readonly int $maxTokens = 2048,
        private readonly int $timeout = 60,
    ) {}

    public function identifyTopics(string $chunkedText): TopicIdentificationResult
    {
        ['system' => $system, 'user' => $user] = $this->promptBuilder->topics($chunkedText);

        $topics = $this->generateStructured(
            $system,
            $user,
            fn (mixed $decoded) => $this->validator->topics($decoded)
        );

        return new TopicIdentificationResult($topics);
    }

    public function explain(array $contextChunks, string $intent, ?string $message = null): string
    {
        ['system' => $system, 'user' => $user] = $this->promptBuilder->explain($contextChunks, $intent, $message);

        return $this->generate($system, $user, function (string $content): string {
            if (trim($content) === '') {
                throw InvalidStructuredOutputException::forCapability('explanation', 'Empty explanation response.');
            }

            return $content;
        });
    }

    public function generateQuiz(array $contextChunks, string $difficulty, int $questionCount, array $subtopicReference = []): QuizGenerationResult
    {
        ['system' => $system, 'user' => $user] = $this->promptBuilder->quiz($contextChunks, $difficulty, $questionCount, $subtopicReference);

        $questions = $this->generateStructured(
            $system,
            $user,
            fn (mixed $decoded) => $this->validator->questions($decoded)
        );

        return new QuizGenerationResult($questions);
    }

    public function evaluateAnswer(string $questionText, string $correctAnswer, string $submittedAnswer): AnswerEvaluationResult
    {
        ['system' => $system, 'user' => $user] = $this->promptBuilder->evaluate($questionText, $correctAnswer, $submittedAnswer);

        $verdict = $this->generateStructured(
            $system,
            $user,
            fn (mixed $decoded) => $this->validator->evaluation($decoded)
        );

        return new AnswerEvaluationResult($verdict['is_correct'], $verdict['feedback']);
    }

    /**
     * Run the retry/fallback flow (AI Architecture §2.3) for a structured
     * capability and return the parsed result.
     */
    private function generateStructured(string $system, string $user, callable $parse): mixed
    {
        return $this->generate($system, $user, function (string $content) use ($parse) {
            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw InvalidStructuredOutputException::forCapability('structured', json_last_error_msg());
            }

            return $parse($decoded);
        });
    }

    /**
     * Attempt the active provider (with the configured retry policy), then the
     * optional fallback provider, consuming the raw content with the callable.
     *
     * @throws AiProviderException
     * @throws InvalidStructuredOutputException
     */
    private function generate(string $system, string $user, callable $consume): mixed
    {
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

        $hadTransportSuccess = false;
        $lastError = null;

        try {
            return $this->attempt($this->primary, $messages, $consume, $hadTransportSuccess, $lastError);
        } catch (ExhaustedProvider) {
            // continue to the optional fallback below
        }

        if ($this->fallback !== null) {
            try {
                return $this->attempt($this->fallback, $messages, $consume, $hadTransportSuccess, $lastError);
            } catch (ExhaustedProvider) {
                // fallback also exhausted — fall through to the final failure handler
            }
        }

        throw $this->finalFailure($hadTransportSuccess, $lastError);
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     *
     * @throws ExhaustedProvider when the provider yielded no valid result within its retry budget
     */
    private function attempt(LLMProviderInterface $provider, array $messages, callable $consume, bool &$hadTransportSuccess, ?Throwable &$lastError): mixed
    {
        for ($i = 0, $attempts = $this->retryAttempts + 1; $i < $attempts; $i++) {
            try {
                $content = $provider->complete($messages, $this->maxTokens, $this->timeout);
                $hadTransportSuccess = true;

                return $consume($content);
            } catch (AiProviderException $e) {
                $lastError = $e;
            } catch (InvalidStructuredOutputException $e) {
                $lastError = $e;
            }

            if ($this->retryDelayMs > 0) {
                usleep($this->retryDelayMs * 1000);
            }
        }

        throw new ExhaustedProvider;
    }

    private function finalFailure(bool $hadTransportSuccess, ?Throwable $lastError): Throwable
    {
        if ($hadTransportSuccess && $lastError instanceof InvalidStructuredOutputException) {
            return $lastError;
        }

        return AiProviderException::unreachable($lastError?->getMessage() ?? 'AI request failed after exhausting all retries and fallback options.');
    }
}

/**
 * Internal signal used to unwind the provider retry loop when a provider did not
 * yield a valid result; never exposed outside ai_service.
 */
final class ExhaustedProvider extends \RuntimeException {}
