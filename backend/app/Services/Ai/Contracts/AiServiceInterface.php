<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Dtos\AnswerEvaluationResult;
use App\Services\Ai\Dtos\QuizGenerationResult;
use App\Services\Ai\Dtos\TopicIdentificationResult;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\InvalidStructuredOutputException;

/**
 * The in-process ai_service contract — the single AI integration boundary of
 * Studyback (AI Architecture §3). Application modules never talk to an LLM
 * provider directly; they call one of these four methods.
 *
 * Method signatures intentionally contain no provider/model parameters:
 * the active provider and model are chosen from environment configuration
 * inside ai_service (LLM Provider Abstraction).
 */
interface AiServiceInterface
{
    /**
     * Identify the topic/subtopic structure of a freshly chunked material.
     *
     * @param  string  $chunkedText  the full chunked text of the new material
     *
     * @throws AiProviderException
     * @throws InvalidStructuredOutputException
     */
    public function identifyTopics(string $chunkedText): TopicIdentificationResult;

    /**
     * Generate a conversational explanation grounded in the retrieved context.
     *
     * @param  list<string>  $contextChunks  retrieved chunk contents, ordered by chunk_index
     *
     * @throws AiProviderException
     */
    public function explain(array $contextChunks, string $intent, ?string $message = null): string;

    /**
     * Generate a set of structured quiz questions from the retrieved context.
     *
     * @param  list<string>  $contextChunks  retrieved chunk contents, ordered by chunk_index
     * @param  list<array{id: int, name: string}>  $subtopicReference  subtopics available in the requested scope, so the AI can reference real ids
     *
     * @throws AiProviderException
     * @throws InvalidStructuredOutputException
     */
    public function generateQuiz(array $contextChunks, string $difficulty, int $questionCount, array $subtopicReference = []): QuizGenerationResult;

    /**
     * Evaluate a submitted answer against the correct answer.
     *
     * @throws AiProviderException
     * @throws InvalidStructuredOutputException
     */
    public function evaluateAnswer(string $questionText, string $correctAnswer, string $submittedAnswer): AnswerEvaluationResult;
}
