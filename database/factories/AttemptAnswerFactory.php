<?php

namespace Database\Factories;

use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttemptAnswer>
 */
class AttemptAnswerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AttemptAnswer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_attempt_id' => AssessmentAttempt::factory(),
            'question_id' => Question::factory(),
            'selected_options' => [$this->faker->numberBetween(1, 4)],
            'answer_text' => $this->faker->optional()->sentence(),
            'is_correct' => $this->faker->boolean(60),
            'points_earned' => $this->faker->numberBetween(0, 5),
        ];
    }

    /**
     * Indicate that the answer is correct.
     */
    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }

    /**
     * Indicate that the answer is incorrect.
     */
    public function incorrect(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => false,
            'points_earned' => 0,
        ]);
    }

    /**
     * Set specific points earned.
     */
    public function points(int $points): static
    {
        return $this->state(fn (array $attributes) => [
            'points_earned' => $points,
        ]);
    }

    /**
     * Set for specific attempt and question.
     */
    public function forAttemptAndQuestion(int $attemptId, int $questionId): static
    {
        return $this->state(fn (array $attributes) => [
            'assessment_attempt_id' => $attemptId,
            'question_id' => $questionId,
        ]);
    }
}
