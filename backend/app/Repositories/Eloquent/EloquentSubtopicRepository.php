<?php

namespace App\Repositories\Eloquent;

use App\Models\Subtopic;
use App\Repositories\Contracts\SubtopicRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentSubtopicRepository implements SubtopicRepositoryInterface
{
    public function findById(int $id): ?Subtopic
    {
        return Subtopic::query()->find($id);
    }

    public function findInTopic(int $topicId, int $subtopicId): ?Subtopic
    {
        return Subtopic::query()
            ->where('topic_id', $topicId)
            ->find($subtopicId);
    }

    public function findBelongsToMaterial(int $materialId, int $subtopicId): ?Subtopic
    {
        return Subtopic::query()
            ->where('subtopics.id', $subtopicId)
            ->whereHas('topic', fn ($topic) => $topic->where('material_id', $materialId))
            ->first();
    }

    public function bulkCreate(array $subtopicsData): Collection
    {
        $subtopics = new Collection;

        foreach ($subtopicsData as $data) {
            $subtopics->push(Subtopic::query()->create([
                'topic_id' => $data['topic_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'order_index' => $data['order_index'] ?? 0,
            ]));
        }

        return $subtopics;
    }

    public function validIdsInTopic(int $topicId, array $subtopicIds): array
    {
        if ($subtopicIds === []) {
            return [];
        }

        return Subtopic::query()
            ->where('topic_id', $topicId)
            ->whereIn('id', $subtopicIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function allInTopic(int $topicId): array
    {
        return Subtopic::query()
            ->where('topic_id', $topicId)
            ->orderBy('order_index')
            ->get()
            ->map(fn (Subtopic $subtopic): array => [
                'id' => $subtopic->id,
                'name' => $subtopic->name,
            ])
            ->all();
    }

    public function recalculateMastery(int $subtopicId): Subtopic
    {
        $average = DB::table('quiz_answers')
            ->join('quiz_questions', 'quiz_questions.id', '=', 'quiz_answers.quiz_question_id')
            ->where('quiz_questions.subtopic_id', $subtopicId)
            ->selectRaw('AVG(CASE WHEN quiz_answers.is_correct THEN 100 ELSE 0 END) AS mastery')
            ->value('mastery');

        $mastery = round((float) $average, 2);

        $status = match (true) {
            $mastery >= 80 => 'mastered',
            $mastery >= 60 => 'in_progress',
            default => 'needs_review',
        };

        $subtopic = Subtopic::query()->findOrFail($subtopicId);
        $subtopic->forceFill([
            'mastery_score' => $mastery,
            'status' => $status,
        ])->save();

        return $subtopic->refresh();
    }
}
