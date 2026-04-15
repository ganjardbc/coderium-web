<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Module>
 */
class ModuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Module::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'title' => $this->faker->sentence(2),
            'description' => $this->faker->paragraph(),
            'order_index' => $this->faker->numberBetween(0, 10),
            'estimated_duration' => $this->faker->numberBetween(30, 300), // 30 minutes to 5 hours
            'is_published' => $this->faker->boolean(70),
        ];
    }

    /**
     * Indicate that the module is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the module is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
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
}
