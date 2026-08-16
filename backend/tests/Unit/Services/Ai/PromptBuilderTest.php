<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\PromptBuilder;
use Tests\TestCase;

/**
 * Prompt construction (AI Architecture §9): role/instruction → retrieved
 * context → task-specific input. Templates are provider-agnostic and the
 * capability marker keeps the Mock AI Provider deterministic.
 */
class PromptBuilderTest extends TestCase
{
    private PromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new PromptBuilder;
    }

    public function test_topics_prompt_contains_capability_marker_and_full_text(): void
    {
        $prompt = $this->builder->topics('chunk one chunk two');

        $this->assertStringContainsString('[CAPABILITY: topic_identification]', $prompt['system']);
        $this->assertStringContainsString('chunk one chunk two', $prompt['user']);
    }

    public function test_explain_prompt_embeds_context_chunks_and_intent(): void
    {
        $prompt = $this->builder->explain(['context chunk A', 'context chunk B'], 'explain', null);

        $this->assertStringContainsString('[CAPABILITY: explanation]', $prompt['system']);
        $this->assertStringContainsString('context chunk A', $prompt['user']);
        $this->assertStringContainsString('context chunk B', $prompt['user']);
        $this->assertStringContainsString('explain', $prompt['user']);
    }

    public function test_explain_prompt_includes_follow_up_message(): void
    {
        $prompt = $this->builder->explain(['ctx'], 'simplify', 'What is a class?');

        $this->assertStringContainsString('What is a class?', $prompt['user']);
    }

    public function test_explain_with_empty_context_instructs_ai_not_to_guess(): void
    {
        $prompt = $this->builder->explain([], 'explain', null);

        $this->assertStringContainsString('does not cover the requested topic', $prompt['system']);
        $this->assertStringContainsString('Do not answer from general knowledge', $prompt['system']);
        $this->assertStringNotContainsString('Material context:', $prompt['user']);
    }

    public function test_explain_with_context_constrains_ai_to_context(): void
    {
        $prompt = $this->builder->explain(['ctx'], 'explain', null);

        $this->assertStringContainsString('Answer only using the provided context chunks', $prompt['system']);
    }

    public function test_quiz_prompt_embeds_count_difficulty_and_subtopic_reference(): void
    {
        $prompt = $this->builder->quiz(
            ['material context'],
            'medium',
            5,
            [['id' => 10, 'name' => 'Polymorphism'], ['id' => 11, 'name' => 'Inheritance']]
        );

        $this->assertStringContainsString('[CAPABILITY: quiz_generation]', $prompt['system']);
        $this->assertStringContainsString('question_count=5', $prompt['user']);
        $this->assertStringContainsString('difficulty=medium', $prompt['user']);
        $this->assertStringContainsString('id=10 name=Polymorphism', $prompt['user']);
        $this->assertStringContainsString('id=11 name=Inheritance', $prompt['user']);
        $this->assertStringContainsString('material context', $prompt['user']);
    }

    public function test_quiz_prompt_without_subtopic_reference_uses_topic_scope(): void
    {
        $prompt = $this->builder->quiz(['material context'], 'easy', 3, []);

        $this->assertStringContainsString('[CAPABILITY: quiz_generation]', $prompt['system']);
        $this->assertStringContainsString('Do NOT include a subtopic_id field', $prompt['system']);
        $this->assertStringContainsString('Scope: the entire topic', $prompt['user']);
        $this->assertStringNotContainsString('Available subtopics:', $prompt['user']);
        $this->assertStringContainsString('material context', $prompt['user']);
    }

    public function test_evaluate_prompt_embeds_question_answers(): void
    {
        $prompt = $this->builder->evaluate('What is 2+2?', '4', '5');

        $this->assertStringContainsString('[CAPABILITY: answer_evaluation]', $prompt['system']);
        $this->assertStringContainsString('question_text = What is 2+2?', $prompt['user']);
        $this->assertStringContainsString('correct_answer = 4', $prompt['user']);
        $this->assertStringContainsString('submitted_answer = 5', $prompt['user']);
    }
}
