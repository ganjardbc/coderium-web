<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Lesson::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->paragraphs(3, true),
            'order_index' => $this->faker->numberBetween(0, 20),
            'estimated_duration' => $this->faker->numberBetween(5, 60), // 5 minutes to 1 hour
            'is_published' => $this->faker->boolean(70),
            'lesson_type' => $this->faker->randomElement(['text', 'video', 'interactive']),
        ];
    }

    /**
     * Indicate that the lesson is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the lesson is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    /**
     * Set lesson type.
     */
    public function type(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'lesson_type' => $type,
        ]);
    }

    /**
     * Set order index.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_index' => $order,
        ]);
    }

    /**
     * Set estimated duration.
     */
    public function duration(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_duration' => $minutes,
        ]);
    }

    /**
     * Create a text lesson.
     */
    public function textLesson(): static
    {
        return $this->state(fn (array $attributes) => [
            'lesson_type' => 'text',
            'content' => $this->faker->paragraphs(5, true),
        ]);
    }

    /**
     * Create a video lesson.
     */
    public function videoLesson(): static
    {
        return $this->state(fn (array $attributes) => [
            'lesson_type' => 'video',
            'content' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Create an interactive lesson.
     */
    public function interactiveLesson(): static
    {
        return $this->state(fn (array $attributes) => [
            'lesson_type' => 'interactive',
            'content' => $this->faker->paragraphs(4, true),
        ]);
    }
}
