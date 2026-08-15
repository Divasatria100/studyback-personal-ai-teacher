<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'original_filename' => 'material-'.fake()->unique()->numberBetween(1, 9999).'.pdf',
            'file_path' => 'materials/'.fake()->unique()->uuid().'.pdf',
            'file_size_bytes' => fake()->numberBetween(1000, 500000),
            'status' => 'ready',
        ];
    }
}
