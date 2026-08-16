<?php

namespace App\Repositories\Eloquent;

use App\Models\Topic;
use App\Repositories\Contracts\TopicRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentTopicRepository implements TopicRepositoryInterface
{
    public function findById(int $id): ?Topic
    {
        return Topic::query()->find($id);
    }

    public function findInMaterial(int $materialId, int $topicId): ?Topic
    {
        return Topic::query()
            ->where('material_id', $materialId)
            ->find($topicId);
    }

    public function treeForMaterial(int $materialId): Collection
    {
        return Topic::query()
            ->with(['subtopics' => fn ($q) => $q->orderBy('order_index')])
            ->where('material_id', $materialId)
            ->orderBy('order_index')
            ->get();
    }

    public function bulkCreateForMaterial(int $materialId, array $topicsData): Collection
    {
        $topics = new Collection;

        foreach ($topicsData as $data) {
            $topics->push(Topic::query()->create([
                'material_id' => $materialId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'order_index' => $data['order_index'] ?? 0,
            ]));
        }

        return $topics;
    }

    public function existingIdsInMaterial(int $materialId, array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        return Topic::query()
            ->where('material_id', $materialId)
            ->whereIn('id', $topicIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function recalculateMastery(int $topicId): Topic
    {
        $average = DB::table('quiz_answers')
            ->join('quiz_questions', 'quiz_questions.id', '=', 'quiz_answers.quiz_question_id')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_questions.quiz_id')
            ->where('quizzes.topic_id', $topicId)
            ->whereNull('quiz_questions.subtopic_id')
            ->selectRaw('AVG(CASE WHEN quiz_answers.is_correct THEN 100 ELSE 0 END) AS mastery')
            ->value('mastery');

        $mastery = round((float) $average, 2);

        $status = match (true) {
            $mastery >= 80 => 'mastered',
            $mastery >= 60 => 'in_progress',
            default => 'needs_review',
        };

        $topic = Topic::query()->findOrFail($topicId);
        $topic->forceFill([
            'mastery_score' => $mastery,
            'status' => $status,
        ])->save();

        return $topic->refresh();
    }
}
