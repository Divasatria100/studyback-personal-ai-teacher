<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Contracts\LLMProviderInterface;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\InvalidStructuredOutputException;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\StructuredOutputValidator;
use Tests\TestCase;

/**
 * ai_service retry/fallback behaviour (AI Architecture §2.3, §11.5, §13):
 * transient failure → retry; persistent failure → optional fallback;
 * both exhausted → classified hard failure; structured output is validated
 * before results reach the application module.
 */
class AiOrchestratorTest extends TestCase
{
    /**
     * Deterministic provider double: scripts of responses/throwables consumed
     * in order; records every invocation (messages, maxTokens, timeout).
     */
    private function provider(): FakeProvider
    {
        return new FakeProvider;
    }

    private function orchestrator(LLMProviderInterface $primary, ?LLMProviderInterface $fallback = null, array $overrides = []): AiOrchestrator
    {
        return new AiOrchestrator(
            primary: $primary,
            fallback: $fallback,
            promptBuilder: new PromptBuilder,
            validator: new StructuredOutputValidator,
            retryAttempts: $overrides['retry_attempts'] ?? 1,
            retryDelayMs: $overrides['retry_delay_ms'] ?? 0,
            maxTokens: $overrides['max_tokens'] ?? 2048,
            timeout: $overrides['timeout'] ?? 60,
        );
    }

    private const TOPICS_JSON = '[{"name":"Fundamentals","description":"Core concepts","subtopics":[{"name":"Key Ideas","description":"Primary ideas"}]}]';

    private const QUESTIONS_JSON = '[{"question_type":"multiple_choice","question_text":"Q1","options":["A","B"],"correct_answer":"A","subtopic_id":1}]';

    // ---------- happy path ----------

    public function test_identify_topics_returns_parsed_result(): void
    {
        $primary = $this->provider()->respond(self::TOPICS_JSON);

        $result = $this->orchestrator($primary)->identifyTopics('material text');

        $this->assertCount(1, $result->topics);
        $this->assertSame('Fundamentals', $result->topics[0]['name']);
    }

    public function test_generate_quiz_returns_parsed_questions(): void
    {
        $primary = $this->provider()->respond(self::QUESTIONS_JSON);

        $result = $this->orchestrator($primary)->generateQuiz(['ctx'], 'medium', 1, []);

        $this->assertCount(1, $result->questions);
        $this->assertSame('Q1', $result->questions[0]['question_text']);
    }

    public function test_evaluate_answer_returns_verdict(): void
    {
        $primary = $this->provider()->respond('{"is_correct":true,"feedback":"Correct"}');

        $result = $this->orchestrator($primary)->evaluateAnswer('question', 'answer', 'answer');

        $this->assertTrue($result->isCorrect);
        $this->assertSame('Correct', $result->feedback);
    }

    public function test_explain_returns_plain_text(): void
    {
        $primary = $this->provider()->respond('The concept is explained here.');

        $explanation = $this->orchestrator($primary)->explain(['ctx'], 'explain');

        $this->assertSame('The concept is explained here.', $explanation);
    }

    // ---------- request construction & config passthrough ----------

    public function test_provides_messages_max_tokens_and_timeout_to_provider(): void
    {
        $primary = $this->provider()->respond(self::TOPICS_JSON);

        $this->orchestrator($primary, null, ['max_tokens' => 512, 'timeout' => 15])
            ->identifyTopics('material text');

        $this->assertSame(512, $primary->lastMaxTokens);
        $this->assertSame(15, $primary->lastTimeout);
        $this->assertCount(2, $primary->lastMessages);
        $this->assertSame('system', $primary->lastMessages[0]['role']);
        $this->assertSame('user', $primary->lastMessages[1]['role']);
    }

    // ---------- retry ----------

    public function test_retries_after_transient_provider_failure(): void
    {
        $primary = $this->provider()
            ->fail(new AiProviderException('temporary'))
            ->respond(self::TOPICS_JSON);

        $result = $this->orchestrator($primary, null, ['retry_attempts' => 2])
            ->identifyTopics('material text');

        $this->assertCount(1, $result->topics);
        $this->assertSame(2, $primary->calls, 'Expected the primary to be called twice (initial + one retry).');
    }

    public function test_exhausted_retries_without_fallback_throw_provider_exception(): void
    {
        $primary = $this->provider()->fail(new AiProviderException('always down'));

        $this->expectException(AiProviderException::class);

        $this->orchestrator($primary, null, ['retry_attempts' => 2])->identifyTopics('material text');

        $this->assertSame(3, $primary->calls);
    }

    // ---------- fallback ----------

    public function test_falls_back_when_primary_exhausts(): void
    {
        $primary = $this->provider()->fail(new AiProviderException('primary down'));
        $fallback = $this->provider()->respond(self::TOPICS_JSON);

        $result = $this->orchestrator($primary, $fallback)->identifyTopics('material text');

        $this->assertCount(1, $result->topics);
        $this->assertSame(2, $primary->calls);
        $this->assertSame(1, $fallback->calls);
    }

    public function test_throws_provider_exception_when_both_fail(): void
    {
        $primary = $this->provider()->fail(new AiProviderException('primary down'));
        $fallback = $this->provider()->fail(new AiProviderException('fallback down'));

        $this->expectException(AiProviderException::class);

        $this->orchestrator($primary, $fallback)->identifyTopics('material text');
    }

    public function test_primary_structured_failure_falls_back_to_valid_provider(): void
    {
        $primary = $this->provider()->respond('not-json-at-all');
        $fallback = $this->provider()->respond(self::TOPICS_JSON);

        $result = $this->orchestrator($primary, $fallback)->identifyTopics('material text');

        $this->assertCount(1, $result->topics);
        $this->assertSame(1, $fallback->calls);
    }

    // ---------- structured output handling ----------

    public function test_malformed_json_without_fallback_throws_structured_exception(): void
    {
        $primary = $this->provider()->respond('{this is not json');

        $this->expectException(InvalidStructuredOutputException::class);
        $this->expectExceptionMessage('structured');

        $this->orchestrator($primary, null, ['retry_attempts' => 0])->identifyTopics('material text');
    }

    public function test_valid_json_wrong_shape_throws_structured_exception(): void
    {
        $primary = $this->provider()->respond('[{"no_name":true}]');

        $this->expectException(InvalidStructuredOutputException::class);
        $this->expectExceptionMessage('topic_identification');

        $this->orchestrator($primary, null, ['retry_attempts' => 0])->identifyTopics('material text');
    }

    public function test_empty_explanation_is_rejected(): void
    {
        $primary = $this->provider()->respond('   ');

        $this->expectException(InvalidStructuredOutputException::class);
        $this->expectExceptionMessage('Empty explanation');

        $this->orchestrator($primary, null, ['retry_attempts' => 0])->explain(['ctx'], 'explain');
    }

    public function test_final_failure_is_provider_exception_when_fallback_unreachable(): void
    {
        $primary = $this->provider()->respond('{invalid json');
        $fallback = $this->provider()->fail(new AiProviderException('fallback timed out'));

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('fallback timed out');

        $this->orchestrator($primary, $fallback, ['retry_attempts' => 0])->identifyTopics('material text');
    }

    public function test_final_failure_is_structured_exception_when_both_return_invalid_shape(): void
    {
        $primary = $this->provider()->respond('[{"no_name":true}]');
        $fallback = $this->provider()->respond('[{"also_broken":true}]');

        $this->expectException(InvalidStructuredOutputException::class);

        $this->orchestrator($primary, $fallback, ['retry_attempts' => 0])->identifyTopics('material text');
    }
}

/**
 * Simple scriptable provider double used only by the tests above.
 */
class FakeProvider implements LLMProviderInterface
{
    public int $calls = 0;

    /** @var list<mixed> */
    private array $queue = [];

    public ?int $lastMaxTokens = null;

    public ?int $lastTimeout = null;

    /** @var list<array{role: string, content: string}>|null */
    public ?array $lastMessages = null;

    public function respond(string $content): self
    {
        $this->queue[] = fn (): string => $content;

        return $this;
    }

    public function fail(\Throwable $e): self
    {
        $this->queue[] = function () use ($e): never {
            throw $e;
        };

        return $this;
    }

    public function complete(array $messages, int $maxTokens = 2048, int $timeout = 60): string
    {
        $this->calls++;
        $this->lastMessages = $messages;
        $this->lastMaxTokens = $maxTokens;
        $this->lastTimeout = $timeout;

        $effect = array_shift($this->queue) ?? throw new AiProviderException('FakeProvider ran out of scripted responses.');

        return $effect();
    }
}
