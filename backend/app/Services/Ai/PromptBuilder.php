<?php

namespace App\Services\Ai;

/**
 * Builds the three-part AI prompts (role/instruction → retrieved context →
 * task-specific input) for each AI capability (AI Architecture §9). Templates
 * are provider-agnostic; the same prompt is sent to whichever provider is
 * configured. Capability markers let the Mock AI Provider stay deterministic.
 */
final class PromptBuilder
{
    /**
     * @return array{system: string, user: string}
     */
    public function topics(string $chunkedText): array
    {
        return [
            'system' => <<<'PROMPT'
            [CAPABILITY: topic_identification]
            You are an assistant that identifies the topic and subtopic structure of a study material.
            Return ONLY valid JSON matching the schema below and nothing else.

            Schema:
            [
              {
                "name": "string",
                "description": "string",
                "subtopics": [
                  { "name": "string", "description": "string" }
                ]
              }
            ]

            The array must not be empty. A topic may have an empty "subtopics" array
            when the material does not break that topic down further; in that case the
            topic itself is the learning unit.
            PROMPT,
            'user' => "Identify the main topics and their subtopics from the following material text.\n\nMaterial:\n".$chunkedText,
        ];
    }

    /**
     * @param  list<string>  $contextChunks
     * @return array{system: string, user: string}
     */
    public function explain(array $contextChunks, string $intent, ?string $message): array
    {
        $emptyContext = $contextChunks === [];

        $system = <<<'PROMPT'
        [CAPABILITY: explanation]
        You are an AI Teacher that explains concepts ONLY using the provided material context.
        PROMPT;

        if ($emptyContext) {
            $system .= "\nThe provided material context does not cover the requested topic. State clearly that the material does not cover this topic. Do not answer from general knowledge.";
        } else {
            $system .= "\nAnswer only using the provided context chunks. If the context does not cover the question, say that the material does not discuss this topic.";
        }

        $user = "Explain in teaching mode: {$intent}\n\n";

        if ($contextChunks !== []) {
            $user .= "Material context:\n".implode("\n\n", $contextChunks)."\n\n";
        }

        if ($message !== null && trim($message) !== '') {
            $user .= "Follow-up question: {$message}\n";
        }

        return ['system' => $system, 'user' => $user];
    }

    /**
     * @param  list<string>  $contextChunks
     * @param  list<array{id: int, name: string}>  $subtopicReference
     * @return array{system: string, user: string}
     */
    public function quiz(array $contextChunks, string $difficulty, int $questionCount, array $subtopicReference): array
    {
        $topicOnly = $subtopicReference === [];

        if ($topicOnly) {
            $system = <<<'PROMPT'
            [CAPABILITY: quiz_generation]
            You are an AI that writes quiz questions ONLY from the provided material context.
            Return ONLY valid JSON matching the schema below and nothing else.

            Schema (array of questions):
            [
              {
                "question_type": "multiple_choice" | "true_false" | "short_answer",
                "question_text": "string",
                "options": ["string", "string", ...] | null,
                "correct_answer": "string"
              }
            ]

            Rules:
            - question_type must be one of multiple_choice, true_false, short_answer.
            - multiple_choice questions MUST include options (at least 2) and correct_answer must be one of the options.
            - true_false and short_answer questions must set options to null.
            - Do NOT include a subtopic_id field. This quiz covers the entire topic.
            PROMPT;

            $user = "Generate {$questionCount} questions with difficulty {$difficulty}.\n\n"
                ."question_count={$questionCount}\n"
                ."difficulty={$difficulty}\n\n"
                ."Scope: the entire topic (no subtopic breakdown).\n\n"
                ."Material context:\n".implode("\n\n", $contextChunks);

            return ['system' => $system, 'user' => $user];
        }

        $lines = array_map(fn (array $s) => sprintf('id=%d name=%s', $s['id'], $s['name']), $subtopicReference);

        $referenceBlock = implode("\n", $lines);

        $system = <<<'PROMPT'
        [CAPABILITY: quiz_generation]
        You are an AI that writes quiz questions ONLY from the provided material context.
        Return ONLY valid JSON matching the schema below and nothing else.

        Schema (array of questions):
        [
          {
            "question_type": "multiple_choice" | "true_false" | "short_answer",
            "question_text": "string",
            "options": ["string", "string", ...] | null,
            "correct_answer": "string",
            "subtopic_id": <integer>
          }
        ]

        Rules:
        - question_type must be one of multiple_choice, true_false, short_answer.
        - multiple_choice questions MUST include options (at least 2) and correct_answer must be one of the options.
        - true_false and short_answer questions must set options to null.
        - subtopic_id MUST be one of the ids listed under "Available subtopics".
        PROMPT;

        $user = "Generate {$questionCount} questions with difficulty {$difficulty}.\n\n"
            ."question_count={$questionCount}\n"
            ."difficulty={$difficulty}\n\n"
            ."Available subtopics:\n{$referenceBlock}\n\n"
            ."Material context:\n".implode("\n\n", $contextChunks);

        return ['system' => $system, 'user' => $user];
    }

    /**
     * @return array{system: string, user: string}
     */
    public function evaluate(string $questionText, string $correctAnswer, string $submittedAnswer): array
    {
        return [
            'system' => <<<'PROMPT'
            [CAPABILITY: answer_evaluation]
            You are a quiz answer evaluator. Return ONLY valid JSON matching the schema below and nothing else.

            Schema:
            { "is_correct": boolean, "feedback": "string" }
            PROMPT,
            'user' => "Evaluate whether the submitted answer matches the correct answer and provide short feedback.\n\n"
                .'question_text = '.$questionText."\n"
                .'correct_answer = '.$correctAnswer."\n"
                .'submitted_answer = '.$submittedAnswer,
        ];
    }
}
