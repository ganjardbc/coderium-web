<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Assignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'instructions' => $this->faker->paragraphs(3, true),
            'evaluation_checklist' => [
                ['item' => 'Code functionality works correctly', 'points' => 1],
                ['item' => 'Code is well documented', 'points' => 1],
                ['item' => 'Tests are comprehensive', 'points' => 1],
            ],
            'due_date' => $this->faker->dateTimeBetween('now', '+2 weeks'),
            'is_published' => true,
        ];
    }

    /**
     * Indicate that the assignment is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    /**
     * Set the assignment as overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('-2 weeks', '-1 day'),
        ]);
    }

    /**
     * Set the assignment for a specific module.
     */
    public function forModule(Module $module): static
    {
        return $this->state(fn (array $attributes) => [
            'module_id' => $module->id,
        ]);
    }

    /**
     * Set a custom due date.
     */
    public function dueAt(\DateTime $dueDate): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $dueDate,
        ]);
    }
}
