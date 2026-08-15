<?php

namespace Database\Factories;

use App\Models\Chunk;
use App\Models\Material;
use App\Models\Subtopic;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'topic_id' => Topic::factory(),
            'subtopic_id' => Subtopic::factory(),
            'content' => fake()->paragraph(),
            'chunk_index' => 0,
        ];
    }
}
