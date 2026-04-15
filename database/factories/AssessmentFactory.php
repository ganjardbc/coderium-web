<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Assessment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assessableType = $this->faker->randomElement(['App\Models\Lesson', 'App\Models\Module']);

        return [
            'assessable_type' => $assessableType,
            'assessable_id' => $assessableType === 'App\Models\Lesson'
                ? Lesson::factory()
                : Module::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'passing_score' => $this->faker->numberBetween(60, 90),
            'max_attempts' => $this->faker->numberBetween(1, 5),
            'time_limit' => $this->faker->optional()->numberBetween(30, 180),
            'is_required' => $this->faker->boolean(70),
        ];
    }

    /**
     * Indicate that the assessment is for a lesson.
     */
    public function forLesson(Lesson $lesson = null): static
    {
        return $this->state(fn (array $attributes) => [
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $lesson?->id ?? Lesson::factory(),
        ]);
    }

    /**
     * Indicate that the assessment is for a module.
     */
    public function forModule(Module $module = null): static
    {
        return $this->state(fn (array $attributes) => [
            'assessable_type' => 'App\Models\Module',
            'assessable_id' => $module?->id ?? Module::factory(),
        ]);
    }

    /**
     * Indicate that the assessment is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Indicate that the assessment is optional.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    /**
     * Set a specific passing score.
     */
    public function passingScore(float $score): static
    {
        return $this->state(fn (array $attributes) => [
            'passing_score' => $score,
        ]);
    }

    /**
     * Set maximum attempts.
     */
    public function maxAttempts(int $attempts): static
    {
        return $this->state(fn (array $attributes) => [
            'max_attempts' => $attempts,
        ]);
    }
}
