<?php

namespace Database\Factories;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoutineFactory extends Factory
{
    protected $model = Routine::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Daily Test Routine',
            'description' => 'Test routine description',
            'category' => 'Health',
            'priority' => 'medium',
            'estimated_duration' => 15,
            'color' => '#74c7c3',
            'enabled' => true,
        ];
    }
}
