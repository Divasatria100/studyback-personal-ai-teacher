<?php

namespace App\Http\Resources;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Quiz payload (API Design §12). correct_answer is intentionally never exposed;
 * questions show answered/is_correct state derived from the answer history.
 * Completed quizzes include the result summary + live topic performance.
 */
class QuizResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Quiz $quiz */
        $quiz = $this->resource;

        $questions = $quiz->questions->map(fn ($question) => [
            'id' => $question->id,
            'question_type' => $question->question_type,
            'question_text' => $question->question_text,
            'options' => $question->options,
            'order_index' => $question->order_index,
            'answered' => $question->answer !== null,
            'is_correct' => $question->answer?->is_correct,
        ])->all();

        $correctCount = collect($questions)->filter(fn ($q) => $q['answered'] && $q['is_correct'])->count();

        $payload = [
            'id' => $quiz->id,
            'status' => $quiz->status,
            'total_questions' => $quiz->total_questions,
            'correct_count' => $correctCount,
            'score' => $quiz->status === 'completed' ? (float) $quiz->score : null,
            'questions' => $questions,
        ];

        if ($quiz->status === 'completed') {
            $payload['completed_at'] = $quiz->completed_at;

            $performance = [];

            foreach ($quiz->questions as $question) {
                if ($question->subtopic_id !== null && $question->subtopic !== null) {
                    $subtopic = $question->subtopic;

                    $performance[$subtopic->id] = [
                        'subtopic_id' => $subtopic->id,
                        'subtopic_name' => $subtopic->name,
                        'mastery_score' => (float) $subtopic->mastery_score,
                        'status' => $subtopic->status,
                    ];
                }
            }

            // Topic-only quizzes surface the topic itself as the performance entry.
            if ($performance === [] && $quiz->topic !== null) {
                $performance[$quiz->topic->id] = [
                    'topic_id' => $quiz->topic->id,
                    'topic_name' => $quiz->topic->name,
                    'mastery_score' => (float) $quiz->topic->mastery_score,
                    'status' => $quiz->topic->status,
                ];
            }

            $payload['topic_performance'] = array_values($performance);
        }

        return $payload;
    }
}
