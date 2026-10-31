<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'title' => fake()->sentence(4),
        'description' => fake()->sentence(12),
        'category' => fake()->randomElement(['SMS', 'USSD', 'Payments', 'Infrastructure', 'Monitoring']),
        'is_active' => true,
        ];
    }
}
