<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\InvalidStructuredOutputException;

/**
 * Validates the shape of structured AI output against the capability schemas in
 * AI Architecture §10. It is independent of the provider/model that produced the
 * output. Business-level validation (e.g. ownership of a subtopic_id) remains in
 * the calling application module.
 */
final class StructuredOutputValidator
{
    public const QUESTION_TYPES = ['multiple_choice', 'true_false', 'short_answer'];

    /**
     * @return list<array{name: string, description: string|null, subtopics: list<array{name: string, description: string|null}>}>
     *
     * @throws InvalidStructuredOutputException
     */
    public function topics(mixed $decoded): array
    {
        if (! is_array($decoded) || $decoded === [] || array_is_list($decoded) === false) {
            throw InvalidStructuredOutputException::forCapability('topic_identification', 'Expected a non-empty array of topics.');
        }

        $topics = [];

        foreach ($decoded as $index => $item) {
            if (! is_array($item) || ! isset($item['name']) || ! is_string($item['name']) || trim($item['name']) === '') {
                throw InvalidStructuredOutputException::forCapability('topic_identification', sprintf('Topic at index %d has no valid name.', $index));
            }

            $subtopicsRaw = $item['subtopics'] ?? null;

            if (! is_array($subtopicsRaw)) {
                throw InvalidStructuredOutputException::forCapability('topic_identification', sprintf('Topic "%s" must declare a "subtopics" array.', $item['name']));
            }

            $subtopics = [];

            foreach ($subtopicsRaw as $subIndex => $subtopic) {
                if (! is_array($subtopic) || ! isset($subtopic['name']) || ! is_string($subtopic['name']) || trim($subtopic['name']) === '') {
                    throw InvalidStructuredOutputException::forCapability('topic_identification', sprintf('Subtopic at index %d has no valid name.', $subIndex));
                }

                $subtopics[] = [
                    'name' => trim($subtopic['name']),
                    'description' => isset($subtopic['description']) && is_string($subtopic['description'])
                        ? trim($subtopic['description'])
                        : null,
                ];
            }

            $topics[] = [
                'name' => trim($item['name']),
                'description' => isset($item['description']) && is_string($item['description'])
                    ? trim($item['description'])
                    : null,
                'subtopics' => $subtopics,
            ];
        }

        $totalSubtopics = array_sum(array_map('count', array_column($topics, 'subtopics')));

        if ($totalSubtopics === 0) {
            throw InvalidStructuredOutputException::forCapability('topic_identification', 'At least one subtopic is required.');
        }

        return $topics;
    }

    /**
     * @return list<array{question_type: string, question_text: string, options: list<string>|null, correct_answer: string, subtopic_id: int}>
     *
     * @throws InvalidStructuredOutputException
     */
    public function questions(mixed $decoded): array
    {
        if (! is_array($decoded) || $decoded === [] || array_is_list($decoded) === false) {
            throw InvalidStructuredOutputException::forCapability('quiz_generation', 'Expected a non-empty array of questions.');
        }

        $questions = [];

        foreach ($decoded as $index => $item) {
            if (! is_array($item)) {
                throw InvalidStructuredOutputException::forCapability('quiz_generation', sprintf('Question at index %d is not an object.', $index));
            }

            $type = $item['question_type'] ?? null;

            if (! is_string($type) || ! in_array($type, self::QUESTION_TYPES, true)) {
                throw InvalidStructuredOutputException::forCapability('quiz_generation', sprintf('Question at index %d has an invalid question_type.', $index));
            }

            $text = $item['question_text'] ?? null;

            if (! is_string($text) || trim($text) === '') {
                throw InvalidStructuredOutputException::forCapability('quiz_generation', sprintf('Question at index %d has no question_text.', $index));
            }

            $correct = $item['correct_answer'] ?? null;

            if (! is_string($correct) || trim($correct) === '') {
                throw InvalidStructuredOutputException::forCapability('quiz_generation', sprintf('Question at index %d has no correct_answer.', $index));
            }

            $subtopicId = $item['subtopic_id'] ?? null;

            if (! is_int($subtopicId) || ! is_numeric($subtopicId) || ! filter_var($subtopicId, FILTER_VALIDATE_INT)) {
                throw InvalidStructuredOutputException::forCapability('quiz_generation', sprintf('Question at index %d has no valid numeric subtopic_id.', $index));
            }

            $options = null;

            if ($type === 'multiple_choice') {
                $optionsRaw = $item['options'] ?? null;

                if (! is_array($optionsRaw) || count($optionsRaw) < 2 || array_is_list($optionsRaw) === false) {
                    throw InvalidStructuredOutputException::forCapability('quiz_generation', sprintf('Question at index %d must have at least 2 options.', $index));
                }

                $options = array_values(array_map('strval', $optionsRaw));
            }

            $questions[] = [
                'question_type' => $type,
                'question_text' => trim($text),
                'options' => $options,
                'correct_answer' => trim($correct),
                'subtopic_id' => (int) $subtopicId,
            ];
        }

        return $questions;
    }

    /**
     * @return array{is_correct: bool, feedback: string}
     *
     * @throws InvalidStructuredOutputException
     */
    public function evaluation(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw InvalidStructuredOutputException::forCapability('answer_evaluation', 'Expected an object verdict.');
        }

        $isCorrect = $decoded['is_correct'] ?? null;

        if (! is_bool($isCorrect)) {
            throw InvalidStructuredOutputException::forCapability('answer_evaluation', '"is_correct" must be a boolean.');
        }

        $feedback = $decoded['feedback'] ?? null;

        if (! is_string($feedback) || trim($feedback) === '') {
            throw InvalidStructuredOutputException::forCapability('answer_evaluation', '"feedback" must be a non-empty string.');
        }

        return [
            'is_correct' => $isCorrect,
            'feedback' => trim($feedback),
        ];
    }
}
