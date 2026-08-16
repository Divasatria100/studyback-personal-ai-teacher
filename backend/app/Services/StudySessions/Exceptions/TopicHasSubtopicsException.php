<?php

namespace App\Services\StudySessions\Exceptions;

use RuntimeException;

/**
 * Raised when a client targets a topic directly but that topic has subtopics.
 * Topics are only learning targets when they have zero subtopics; otherwise the
 * subtopics are the learning targets. Maps to HTTP 422 Unprocessable Entity.
 */
final class TopicHasSubtopicsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This topic has subtopics; select a subtopic as the learning target instead.');
    }
}