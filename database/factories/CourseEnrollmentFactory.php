<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseEnrollment>
 */
class CourseEnrollmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CourseEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enrolledAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $isCompleted = $this->faker->boolean(30); // 30% chance of completion

        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'enrolled_at' => $enrolledAt,
            'completed_at' => $isCompleted ? $this->faker->dateTimeBetween($enrolledAt, 'now') : null,
            'progress_percentage' => $isCompleted ? 100.00 : $this->faker->randomFloat(2, 0, 99.99),
        ];
    }

    /**
     * Indicate that the enrollment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => $this->faker->dateTimeBetween($attributes['enrolled_at'] ?? '-1 month', 'now'),
            'progress_percentage' => 100.00,
        ]);
    }

    /**
     * Indicate that the enrollment is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => null,
            'progress_percentage' => $this->faker->randomFloat(2, 1, 99),
        ]);
    }

    /**
     * Set specific progress percentage.
     */
    public function progress(float $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percentage' => $percentage,
            'completed_at' => $percentage >= 100 ? now() : null,
        ]);
    }
}
