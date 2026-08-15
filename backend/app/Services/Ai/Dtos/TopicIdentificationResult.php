<?php

namespace App\Services\Ai\Dtos;

/**
 * Structured result of topic/subtopic identification (AI Architecture §10.1).
 *
 * @phpstan-type SubtopicShape array{name: string, description: string|null}
 * @phpstan-type TopicShape array{name: string, description: string|null, subtopics: list<SubtopicShape>}
 */
final class TopicIdentificationResult
{
    /**
     * @param  list<TopicShape>  $topics
     */
    public function __construct(public readonly array $topics) {}
}
