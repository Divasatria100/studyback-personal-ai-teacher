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
            [$topicIdBySubtopic, $subtopicFlattened] = $this->persistTopicsAndSubtopics($materialId, $identification);

            $this->persistChunks($materialId, $chunks, $subtopicFlattened, $topicIdBySubtopic);

            $this->materials->updateProcessingState($materialId, 'ready');
        });
    }

    /**
     * @return array{0: array<int, int>, 1: list<array{topicId: int, subtopicId: int}>}
     */
    private function persistTopicsAndSubtopics(int $materialId, TopicIdentificationResult $identification): array
    {
        $topicsData = [];
        $subtopicsData = [];

        foreach ($identification->topics as $topicIndex => $topic) {
            $topicsData[] = [
                'name' => $topic['name'],
                'description' => $topic['description'],
                'order_index' => $topicIndex,
            ];

            foreach ($topic['subtopics'] as $subindex => $subtopic) {
                $subtopicsData[] = [
                    'topic_id' => 0,
                    'name' => $subtopic['name'],
                    'description' => $subtopic['description'],
                    'order_index' => $subindex,
                ];
            }
        }

        if ($subtopicsData === []) {
            throw new \RuntimeException('AI returned zero subtopics; the material cannot be marked ready.');
        }

        $createdTopics = $this->topics->bulkCreateForMaterial($materialId, $topicsData);

        $flattenedByOrder = [];
        $orderIndex = 0;

        foreach ($createdTopics as $topicIndex => $topic) {
            $subtopicCount = count($identification->topics[$topicIndex]['subtopics']);

            for ($i = 0; $i < $subtopicCount; $i++) {
                $subtopicsData[$orderIndex]['topic_id'] = $topic->id;
                $flattenedByOrder[$orderIndex] = ['topicId' => $topic->id];
                $orderIndex++;
            }
        }

        $createdSubtopics = $this->subtopics->bulkCreate($subtopicsData);

        $topicIdBySubtopic = [];

        foreach ($createdSubtopics as $orderIndex => $subtopic) {
            $flattenedByOrder[$orderIndex]['subtopicId'] = $subtopic->id;
            $topicIdBySubtopic[$subtopic->id] = $subtopic->topic_id;
        }

        return [$topicIdBySubtopic, array_values($flattenedByOrder)];
    }

    /**
     * @param  list<string>  $chunks
     * @param  list<array{topicId: int, subtopicId: int}>  $subtopicFlattened
     * @param  array<int, int>  $topicIdBySubtopic
     */
    private function persistChunks(
        int $materialId,
        array $chunks,
        array $subtopicFlattened,
        array $topicIdBySubtopic
    ): void {
        $assignments = $this->assigner->assign($subtopicFlattened, count($chunks));

        $rows = [];

        foreach ($chunks as $chunkIndex => $content) {
            $subtopicId = $assignments[$chunkIndex];

            $topicId = $topicIdBySubtopic[$subtopicId] ?? null;

            if ($topicId === null) {
                throw new \RuntimeException('Unable to map a chunk to a topic.');
            }

            $rows[] = [
                'material_id' => $materialId,
                'topic_id' => $topicId,
                'subtopic_id' => $subtopicId,
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
