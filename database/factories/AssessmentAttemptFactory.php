<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssessmentAttempt>
 */
class AssessmentAttemptFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AssessmentAttempt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $completedAt = $this->faker->optional(0.8)->dateTimeBetween($startedAt, 'now');

        return [
            'user_id' => User::factory(),
            'assessment_id' => Assessment::factory(),
            'score' => $this->faker->numberBetween(0, 100),
            'max_score' => 100,
            'passed' => $this->faker->boolean(70),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'time_taken' => $completedAt ? $this->faker->numberBetween(300, 3600) : null,
            'attempt_number' => $this->faker->numberBetween(1, 3),
        ];
    }

    /**
     * Indicate that the attempt is completed and passed.
     */
    public function passed(): static
    {
        return $this->state(fn (array $attributes) => [
            'passed' => true,
            'score' => $this->faker->numberBetween(70, 100),
            'completed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the attempt is completed but failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'passed' => false,
            'score' => $this->faker->numberBetween(0, 69),
            'completed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the attempt is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => null,
            'time_taken' => null,
        ]);
    }

    /**
     * Set specific score.
     */
    public function score(int $score): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $score,
        ]);
    }

    /**
     * Set for specific user and assessment.
     */
    public function forUserAndAssessment(int $userId, int $assessmentId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
            'assessment_id' => $assessmentId,
        ]);
    }
}
