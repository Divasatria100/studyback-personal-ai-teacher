<?php

namespace App\Services\Materials;

use App\Repositories\Contracts\ChunkRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\SubtopicRepositoryInterface;
use App\Repositories\Contracts\TopicRepositoryInterface;
use App\Services\Ai\Contracts\AiServiceInterface;
use App\Services\Ai\Dtos\TopicIdentificationResult;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\InvalidStructuredOutputException;
use App\Services\Processing\ChunkSubtopicAssigner;
use App\Services\Processing\Exceptions\PdfExtractionException;
use App\Services\Processing\PdfTextExtractor;
use App\Services\Processing\TextChunker;
use App\Services\Processing\TextCleaner;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orchestrates the synchronous upload processing pipeline (API Design §9):
 * extract → clean → chunk → AI topic/subtopic identification → persist in one
 * transaction (Database Design §10, §15). Owns the materials.status transition.
 *
 * The AI provider never writes to the database; on any failure the whole
 * transaction is rolled back and the material is marked 'failed' in a separate,
 * single update — no partial material is ever left behind.
 */
final class MaterialProcessingService
{
    public function __construct(
        private readonly MaterialRepositoryInterface $materials,
        private readonly TopicRepositoryInterface $topics,
        private readonly SubtopicRepositoryInterface $subtopics,
        private readonly ChunkRepositoryInterface $chunks,
        private readonly PdfTextExtractor $extractor,
        private readonly TextCleaner $cleaner,
        private readonly TextChunker $chunker,
        private readonly ChunkSubtopicAssigner $assigner,
        private readonly AiServiceInterface $ai,
    ) {}

    /**
     * Process a freshly-uploaded material whose row already exists (status 'processing').
     *
     * @param  int  $materialId  id of the material row created by the controller
     * @param  string  $pdfPath  absolute path to the stored PDF on the disk
     * @return bool true on success, false when the pipeline failed (material now 'failed')
     */
    public function process(int $materialId, string $pdfPath): bool
    {
        $chunks = null;

        try {
            $chunks = $this->extractAndChunk($pdfPath);

            $chunkedText = implode("\n\n", $chunks);

            $identification = $this->ai->identifyTopics($chunkedText);

            $this->persist($materialId, $chunks, $identification);

            return true;
        } catch (AiProviderException $e) {
            $this->markFailed($materialId, $e);

            throw $e;
        } catch (Throwable $e) {
            $this->markFailed($materialId, $e);

            return false;
        }
    }

    /**
     * @return list<string> cleaned, deterministically chunked in-memory text
     */
    private function extractAndChunk(string $pdfPath): array
    {
        $raw = $this->extractor->extract($pdfPath);

        $cleaned = $this->cleaner->clean($raw);

        $chunks = $this->chunker->chunk($cleaned);

        if ($chunks === []) {
            throw new \RuntimeException('The material produced no chunks after cleaning.');
        }

        return $chunks;
    }

    /**
     * @param  list<string>  $chunks
     */
    private function persist(int $materialId, array $chunks, TopicIdentificationResult $identification): void
    {
        DB::transaction(function () use ($materialId, $chunks, $identification) {
            $targets = $this->persistTopicsAndSubtopics($materialId, $identification);

            $this->persistChunks($materialId, $chunks, $targets);

            $this->materials->updateProcessingState($materialId, 'ready');
        });
    }

    /**
     * Persist topics and subtopics and return the flattened learning targets in
     * document order. Topics with subtopics contribute one target per subtopic;
     * topics without subtopics contribute a single topic-only target
     * (subtopicId = null) so they remain learnable.
     *
     * @return list<array{topicId: int, subtopicId: int|null}>
     */
    private function persistTopicsAndSubtopics(int $materialId, TopicIdentificationResult $identification): array
    {
        $topicsData = [];
        $subtopicsData = [];
        $subtopicCounts = [];

        foreach ($identification->topics as $topicIndex => $topic) {
            $topicsData[] = [
                'name' => $topic['name'],
                'description' => $topic['description'],
                'order_index' => $topicIndex,
            ];

            $subtopicCounts[$topicIndex] = count($topic['subtopics']);

            foreach ($topic['subtopics'] as $subindex => $subtopic) {
                $subtopicsData[] = [
                    'topic_id' => 0,
                    'name' => $subtopic['name'],
                    'description' => $subtopic['description'],
                    'order_index' => $subindex,
                ];
            }
        }

        $createdTopics = $this->topics->bulkCreateForMaterial($materialId, $topicsData);

        $subtopicOrder = 0;

        foreach ($createdTopics as $topicIndex => $topic) {
            for ($i = 0; $i < $subtopicCounts[$topicIndex]; $i++) {
                $subtopicsData[$subtopicOrder]['topic_id'] = $topic->id;
                $subtopicOrder++;
            }
        }

        $createdSubtopics = $this->subtopics->bulkCreate($subtopicsData);

        $targets = [];
        $subtopicOrder = 0;

        foreach ($createdTopics as $topicIndex => $topic) {
            $count = $subtopicCounts[$topicIndex];

            if ($count === 0) {
                $targets[] = ['topicId' => $topic->id, 'subtopicId' => null];

                continue;
            }

            for ($i = 0; $i < $count; $i++) {
                $targets[] = [
                    'topicId' => $topic->id,
                    'subtopicId' => $createdSubtopics[$subtopicOrder]->id,
                ];
                $subtopicOrder++;
            }
        }

        return $targets;
    }

    /**
     * @param  list<string>  $chunks
     * @param  list<array{topicId: int, subtopicId: int|null}>  $targets
     */
    private function persistChunks(int $materialId, array $chunks, array $targets): void
    {
        $assignments = $this->assigner->assign($targets, count($chunks));

        $rows = [];

        foreach ($chunks as $chunkIndex => $content) {
            $assignment = $assignments[$chunkIndex];

            $rows[] = [
                'material_id' => $materialId,
                'topic_id' => $assignment['topicId'],
                'subtopic_id' => $assignment['subtopicId'],
                'content' => $content,
                'chunk_index' => $chunkIndex,
            ];
        }

        $this->chunks->bulkCreate($rows);
    }

    private function markFailed(int $materialId, Throwable $e): void
    {
        $reason = match (true) {
            $e instanceof PdfExtractionException => $e->getMessage(),
            $e instanceof AiProviderException => 'AI provider unavailable: '.$e->getMessage(),
            $e instanceof InvalidStructuredOutputException => 'AI returned an invalid topic structure: '.$e->getMessage(),
            default => $e->getMessage(),
        };

        $this->materials->updateProcessingState($materialId, 'failed', $reason);
    }
}
