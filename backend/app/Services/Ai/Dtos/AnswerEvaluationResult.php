<?php

namespace App\Services\Ai\Dtos;

/**
 * Structured verdict of a single answer evaluation (AI Architecture §10.3).
 * The AI verdict is treated as input to Laravel's deterministic scoring, not
 * as the final authority on Learning State.
 */
final class AnswerEvaluationResult
{
    public function __construct(
        public readonly bool $isCorrect,
        public readonly string $feedback,
    ) {}
}
