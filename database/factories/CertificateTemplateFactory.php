<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificateTemplate>
 */
class CertificateTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CertificateTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Certificate',
            'description' => $this->faker->sentence(),
            'template_content' => '<html><body>Certificate content</body></html>',
            'template_data' => json_encode([
                'background_color' => $this->faker->hexColor(),
                'text_color' => $this->faker->hexColor(),
                'font_family' => $this->faker->randomElement(['Arial', 'Times New Roman', 'Helvetica']),
                'border_style' => $this->faker->randomElement(['solid', 'dashed', 'dotted']),
            ]),
            'is_active' => $this->faker->boolean(90),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the template is for tracks.
     */
    public function forTrack(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_type' => 'track',
        ]);
    }

    /**
     * Indicate that the template is for courses.
     */
    public function forCourse(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_type' => 'course',
        ]);
    }

    /**
     * Indicate that the template is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
