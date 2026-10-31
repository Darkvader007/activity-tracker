<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'activity_id' => Activity::factory(),
            'user_id' => User::factory(),
            'status' => fake()->randomElement(ActivityStatus::cases()),
            'remark' => fake()->optional()->sentence(),
            'activity_date' => fake()->dateTimeBetween('-14 days', 'now'),
        ];
    }
}
