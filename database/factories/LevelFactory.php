<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Level>
 */
class LevelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Level::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'track_id' => Track::factory(),
            'title' => $this->faker->sentence(2),
            'description' => $this->faker->paragraph(),
            'difficulty' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'order_index' => $this->faker->numberBetween(0, 10),
            'is_published' => $this->faker->boolean(70),
        ];
    }

    /**
     * Indicate that the level is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the level is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    /**
     * Set difficulty level.
     */
    public function difficulty(string $level): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => $level,
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
