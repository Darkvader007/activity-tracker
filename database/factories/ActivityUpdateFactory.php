<?php

namespace Database\Factories;

use App\Models\ActivityUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\ActivityStatus;

/**
 * @extends Factory<ActivityUpdate>
 */
class ActivityUpdateFactory extends Factory
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
            'activity_id' => \App\Models\Activity::factory(),
            'user_id' => \App\Models\User::factory(),
            'status' => fake()->randomElement(ActivityStatus::cases()),
            'remark' => fake()->optional()->sentence(),
            'activity_date' => fake()->dateTimeBetween('-14 days', 'now'),
        ];
    }
}
