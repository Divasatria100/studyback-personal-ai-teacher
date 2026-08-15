<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'order_index' => 0,
        ];
    }
}
