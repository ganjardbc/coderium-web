<?php

namespace Database\Factories;

use App\Models\LearningProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningProgress>
 */
class LearningProgressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LearningProgress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $completionPercentage = $this->faker->numberBetween(0, 100);
        $isCompleted = $completionPercentage >= 100;

        return [
            'user_id' => User::factory(),
            'progressable_type' => 'App\Models\Course', // Default, will be overridden
            'progressable_id' => 1, // Default, will be overridden
            'completion_percentage' => $completionPercentage,
            'time_spent_minutes' => $this->faker->numberBetween(5, 480), // 5 minutes to 8 hours
            'engagement_score' => $this->faker->randomFloat(2, 0, 1), // 0.00 to 1.00
            'last_accessed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'completed_at' => $isCompleted ? $this->faker->dateTimeBetween('-7 days', 'now') : null,
        ];
    }

    /**
     * Indicate that the progress is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_percentage' => 100,
            'completed_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Indicate that the progress is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_percentage' => $this->faker->numberBetween(1, 99),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the progress is just started.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_percentage' => $this->faker->numberBetween(1, 25),
            'completed_at' => null,
        ]);
    }
}
