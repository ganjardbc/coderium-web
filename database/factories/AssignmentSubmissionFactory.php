<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AssignmentSubmission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submittedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $isGraded = $this->faker->boolean(70);

        return [
            'user_id' => User::factory(),
            'assignment_id' => Assignment::factory(),
            'repository_url' => $this->faker->optional()->url(),
            'file_attachments' => $this->faker->optional()->randomElements(['file1.pdf', 'file2.docx', 'screenshot.png'], rand(0, 3)),
            'submission_notes' => $this->faker->paragraphs(3, true),
            'submitted_at' => $submittedAt,
            'grade' => $isGraded ? $this->faker->numberBetween(60, 100) : null,
            'feedback' => $isGraded ? $this->faker->paragraph() : null,
            'graded_at' => $isGraded ? $this->faker->dateTimeBetween($submittedAt, 'now') : null,
        ];
    }

    /**
     * Indicate that the submission is graded.
     */
    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'grade' => $this->faker->numberBetween(60, 100),
            'feedback' => $this->faker->paragraph(),
            'graded_at' => $this->faker->dateTimeBetween($attributes['submitted_at'] ?? '-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the submission is not graded yet.
     */
    public function ungraded(): static
    {
        return $this->state(fn (array $attributes) => [
            'grade' => null,
            'feedback' => null,
            'graded_at' => null,
        ]);
    }

    /**
     * Set specific grade.
     */
    public function grade(int $grade): static
    {
        return $this->state(fn (array $attributes) => [
            'grade' => $grade,
            'feedback' => $this->faker->paragraph(),
            'graded_at' => $this->faker->dateTimeBetween($attributes['submitted_at'] ?? '-30 days', 'now'),
        ]);
    }

    /**
     * Set for specific user and assignment.
     */
    public function forUserAndAssignment(int $userId, int $assignmentId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
            'assignment_id' => $assignmentId,
        ]);
    }

    /**
     * Set as late submission.
     */
    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }
}
