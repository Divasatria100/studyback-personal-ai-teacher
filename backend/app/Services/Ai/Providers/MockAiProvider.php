<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LLMProviderInterface;
use App\Services\Ai\Exceptions\AiProviderException;

/**
 * Mock AI Provider — deterministic dev/test provider that never calls an
 * external API (Tech Stack Specification §7.1, §7.2). It inspects the prompt
 * capability marker to return fixed, well-formed responses matching each
 * structured-output schema so automated tests never depend on a real provider.
 */
class MockAiProvider implements LLMProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected readonly array $config) {}

    public function complete(array $messages, int $maxTokens = 2048, int $timeout = 60): string
    {
        $system = '';
        $user = '';

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system .= $message['content']."\n";
            } else {
                $user .= $message['content']."\n";
            }
        }

        if ($this->config['failure'] ?? false) {
            throw new AiProviderException('Mock AI provider configured to fail.');
        }

        if (str_contains($system, '[CAPABILITY: topic_identification]')) {
            return $this->response('override_topics', $this->defaultTopics());
        }

        if (str_contains($system, '[CAPABILITY: quiz_generation]')) {
            return $this->response('override_questions', $this->defaultQuestions($user));
        }

        if (str_contains($system, '[CAPABILITY: answer_evaluation]')) {
            return $this->response('override_evaluation', $this->defaultEvaluation($user));
        }

        return $this->response('override_explanation', 'This is a mock explanation. Based on the provided material context, the key concept is explained here and you can ask follow-up questions for more depth.');
    }

    private function response(string $overrideKey, string $default): string
    {
        $override = $this->config[$overrideKey] ?? null;

        if (is_string($override) && trim($override) !== '') {
            return $override;
        }

        return $default;
    }

    private function defaultTopics(): string
    {
        return <<<'JSON'
        [
            {
                "name": "Fundamentals",
                "description": "Core concepts covered by the material.",
                "subtopics": [
                    {
                        "name": "Key Ideas",
                        "description": "Primary ideas and definitions presented in the material."
                    }
                ]
            }
        ]
        JSON;
    }

    /**
     * Build a deterministic question set that references the real subtopic ids
     * embedded in the prompt's "Available subtopics" reference block.
     */
    private function defaultQuestions(string $user): string
    {
        preg_match_all('/id=(\d+)\s+name=([^\n]+)/', $user, $matches, PREG_SET_ORDER);

        $subtopics = array_map(fn ($match) => ['id' => (int) $match[1], 'name' => trim($match[2])], $matches);

        if ($subtopics === []) {
            $subtopics = [['id' => 1, 'name' => 'Default Subtopic']];
        }

        $requestedCount = 3;
        if (preg_match('/question_count=(\d+)/', $user, $countMatch)) {
            $requestedCount = (int) $countMatch[1];
        }

        $questions = [];

        for ($i = 0; $i < max(1, $requestedCount); $i++) {
            $subtopic = $subtopics[$i % count($subtopics)];

            $questions[] = [
                'question_type' => 'multiple_choice',
                'question_text' => sprintf('Which statement best reflects the material about "%s"?', $subtopic['name']),
                'options' => ['Yes', 'No', 'Maybe', 'Not sure'],
                'correct_answer' => 'Yes',
                'subtopic_id' => $subtopic['id'],
            ];
        }

        return json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function defaultEvaluation(string $user): string
    {
        $correct = null;
        $submitted = null;

        if (preg_match('/correct_answer\s*=\s*(.+)/', $user, $correctMatch)) {
            $correct = trim($correctMatch[1]);
        }

        if (preg_match('/submitted_answer\s*=\s*(.+)/', $user, $submittedMatch)) {
            $submitted = trim($submittedMatch[1]);
        }

        $isCorrect = $correct !== null && strcasecmp($correct, (string) $submitted) === 0;

        $feedback = $isCorrect
            ? 'Correct — your answer matches the expected answer.'
            : 'Not quite — review the material again and try comparing your answer with the key answer.';

        return json_encode([
            'is_correct' => $isCorrect,
            'feedback' => $feedback,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
