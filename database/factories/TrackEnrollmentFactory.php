<?php

namespace Database\Factories;

use App\Models\Track;
use App\Models\TrackEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrackEnrollment>
 */
class TrackEnrollmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TrackEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'track_id' => Track::factory(),
            'enrolled_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
            'progress_percentage' => $this->faker->numberBetween(0, 100),
            'completed_at' => $this->faker->optional(0.2)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the enrollment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percentage' => 100,
            'completed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the enrollment is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percentage' => $this->faker->numberBetween(1, 99),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the enrollment just started.
     */
    public function justStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percentage' => 0,
            'completed_at' => null,
        ]);
    }

    /**
     * Set specific progress percentage.
     */
    public function progress(int $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percentage' => $percentage,
            'completed_at' => $percentage >= 100 ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
        ]);
    }

    /**
     * Set for specific user and track.
     */
    public function forUserAndTrack(int $userId, int $trackId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
            'track_id' => $trackId,
        ]);
    }
}
