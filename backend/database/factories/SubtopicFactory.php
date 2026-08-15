<?php

namespace Database\Factories;

use App\Models\Subtopic;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subtopic>
 */
class SubtopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'order_index' => 0,
            'mastery_score' => 0,
            'status' => 'not_started',
        ];
    }
}
