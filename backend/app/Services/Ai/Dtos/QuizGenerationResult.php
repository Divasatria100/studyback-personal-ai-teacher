<?php

namespace App\Services\Ai\Dtos;

/**
 * Structured result of quiz question generation (AI Architecture §10.2).
 *
 * @phpstan-type QuestionShape array{
 *     question_type: string,
 *     question_text: string,
 *     options: list<string>|null,
 *     correct_answer: string,
 *     subtopic_id: int,
 *     order_index?: int
 * }
 */
final class QuizGenerationResult
{
    /**
     * @param  list<QuestionShape>  $questions
     */
    public function __construct(public readonly array $questions) {}
}
