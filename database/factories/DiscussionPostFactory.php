<?php

namespace Database\Factories;

use App\Models\Discussion;
use App\Models\DiscussionPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscussionPost>
 */
class DiscussionPostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DiscussionPost::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discussion_id' => Discussion::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'content' => $this->faker->paragraphs(2, true),
            'is_instructor_response' => false,
            'is_moderated' => false,
        ];
    }

    /**
     * Indicate that the post is an instructor response.
     */
    public function instructorResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_instructor_response' => true,
        ]);
    }

    /**
     * Indicate that the post is moderated.
     */
    public function moderated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_moderated' => true,
        ]);
    }

    /**
     * Set the post as a reply to another post.
     */
    public function replyTo(DiscussionPost $parentPost): static
    {
        return $this->state(fn (array $attributes) => [
            'discussion_id' => $parentPost->discussion_id,
            'parent_id' => $parentPost->id,
        ]);
    }

    /**
     * Set the discussion for the post.
     */
    public function forDiscussion(Discussion $discussion): static
    {
        return $this->state(fn (array $attributes) => [
            'discussion_id' => $discussion->id,
        ]);
    }

    /**
     * Set the user for the post.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'is_instructor_response' => $user->role === 'instructor',
        ]);
    }
}
