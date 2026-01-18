<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LessonProgress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lesson_id' => Lesson::factory(),
            'completed_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'time_spent' => $this->faker->numberBetween(300, 3600), // 5 minutes to 1 hour
        ];
    }

    /**
     * Indicate that the lesson is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the lesson is not completed.
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => null,
        ]);
    }

    /**
     * Set specific time spent.
     */
    public function timeSpent(int $seconds): static
    {
        return $this->state(fn (array $attributes) => [
            'time_spent' => $seconds,
        ]);
    }

    /**
     * Set for specific user and lesson.
     */
    public function forUserAndLesson(int $userId, int $lessonId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
            'lesson_id' => $lessonId,
        ]);
    }
}
