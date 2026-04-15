<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Course::class;

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
            'slug' => Str::slug($title) . '-' . $this->faker->unique()->randomNumber(4),
            'is_active' => $this->faker->boolean(80),
            'certificate_template_id' => null, // Will be set if needed
            'estimated_duration' => $this->faker->numberBetween(60, 480), // 1 to 8 hours
        ];
    }

    /**
     * Indicate that the course is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the course is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
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
     * Associate with a certificate template.
     */
    public function withCertificateTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_template_id' => CertificateTemplate::factory(),
        ]);
    }
}
