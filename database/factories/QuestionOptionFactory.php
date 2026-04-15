<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = QuestionOption::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'option_text' => $this->faker->sentence(3),
            'is_correct' => false,
            'order_index' => $this->faker->numberBetween(0, 5),
        ];
    }

    /**
     * Indicate that the option is correct.
     */
    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }

    /**
     * Indicate that the option is incorrect.
     */
    public function incorrect(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => false,
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
     * Create a true option for true/false questions.
     */
    public function trueOption(): static
    {
        return $this->state(fn (array $attributes) => [
            'option_text' => 'True',
            'is_correct' => true,
        ]);
    }

    /**
     * Create a false option for true/false questions.
     */
    public function falseOption(): static
    {
        return $this->state(fn (array $attributes) => [
            'option_text' => 'False',
            'is_correct' => false,
        ]);
    }
}
