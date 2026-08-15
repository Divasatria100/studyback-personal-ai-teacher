<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudySession>
 */
class StudySessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'material_id' => Material::factory(),
            'mode' => 'guided_study_session',
            'difficulty' => 'medium',
            'status' => 'active',
            'started_at' => now(),
        ];
    }
}
