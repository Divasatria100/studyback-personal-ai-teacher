<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\StudySession;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_session_id' => StudySession::factory(),
            'topic_id' => Topic::factory(),
            'subtopic_id' => null,
            'difficulty' => 'medium',
            'status' => 'in_progress',
            'total_questions' => 5,
        ];
    }
}
