<?php

namespace App\Services\Quizzes\Exceptions;

use RuntimeException;

/**
 * Raised when submitting an answer for a question that already has one, or for
 * a quiz that is already completed. Maps to HTTP 409 Conflict (API Design §12).
 */
final class QuizAnswerConflictException extends RuntimeException
{
    public static function questionAlreadyAnswered(): self
    {
        return new self('This question has already been answered.');
    }

    public static function quizAlreadyCompleted(): self
    {
        return new self('This quiz is already completed.');
    }
}
