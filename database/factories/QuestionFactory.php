<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Question::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'question_text' => $this->faker->sentence() . '?',
            'question_type' => $this->faker->randomElement(['multiple_choice', 'true_false', 'code_output', 'conceptual']),
            'points' => $this->faker->numberBetween(1, 5),
            'order_index' => $this->faker->numberBetween(0, 10),
            'explanation' => $this->faker->optional()->paragraph(),
        ];
    }

    /**
     * Indicate that the question is multiple choice.
     */
    public function multipleChoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => 'multiple_choice',
        ]);
    }

    /**
     * Indicate that the question is true/false.
     */
    public function trueFalse(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => 'true_false',
        ]);
    }

    /**
     * Indicate that the question is code output.
     */
    public function codeOutput(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => 'code_output',
        ]);
    }

    /**
     * Indicate that the question is conceptual.
     */
    public function conceptual(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => 'conceptual',
        ]);
    }

    /**
     * Set specific points value.
     */
    public function points(int $points): static
    {
        return $this->state(fn (array $attributes) => [
            'points' => $points,
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
}
