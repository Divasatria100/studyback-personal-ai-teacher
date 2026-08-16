<?php

namespace App\Http\Resources;

use App\Models\Subtopic;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Topic/subtopic tree with current mastery/status (API Design §10) — powers
 * the Learning Map sidebar and Material Detail topic list.
 */
class TopicTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Topic $topic */
        $topic = $this->resource;

        return [
            'id' => $topic->id,
            'name' => $topic->name,
            'description' => $topic->description,
            'order_index' => $topic->order_index,
            'mastery_score' => (float) $topic->mastery_score,
            'status' => $topic->status,
            'subtopics' => $topic->subtopics->map(
                fn (Subtopic $subtopic): array => [
                    'id' => $subtopic->id,
                    'name' => $subtopic->name,
                    'description' => $subtopic->description,
                    'order_index' => $subtopic->order_index,
                    'mastery_score' => (float) $subtopic->mastery_score,
                    'status' => $subtopic->status,
                ]
            )->all(),
        ];
    }
}
