<?php

namespace Database\Factories;

use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Track>
 */
class TrackFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Track::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'title' => $title,
            'description' => $this->faker->paragraph(),
            'slug' => Str::slug($title) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'is_premium' => $this->faker->boolean(30),
            'price' => $this->faker->optional(0.3)->randomFloat(2, 9.99, 199.99),
            'is_published' => $this->faker->boolean(70),
            'difficulty_level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'estimated_duration' => $this->faker->numberBetween(60, 2400), // 1 hour to 40 hours
            'instructor_id' => User::factory()->state(['role' => 'instructor']),
        ];
    }

    /**
     * Indicate that the track is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the track is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    /**
     * Indicate that the track is premium.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => true,
            'price' => $this->faker->randomFloat(2, 19.99, 299.99),
        ]);
    }

    /**
     * Indicate that the track is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => false,
            'price' => null,
        ]);
    }

    /**
     * Set difficulty level.
     */
    public function difficulty(string $level): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => $level,
        ]);
    }
}
