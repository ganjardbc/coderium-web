<?php

namespace Tests\Unit\Services;

use App\Models\Discussion;
use App\Models\DiscussionPost;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Track;
use App\Models\User;
use App\Services\DiscussionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DiscussionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DiscussionService $discussionService;
    private User $instructor;
    private User $learner;
    private Track $track;
    private Lesson $lesson;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discussionService = new DiscussionService();

        // Create test users
        $this->instructor = User::factory()->create(['role' => 'instructor']);
        $this->learner = User::factory()->create(['role' => 'learner']);

        // Create test content hierarchy
        $this->track = Track::factory()->create(['instructor_id' => $this->instructor->id]);
        $level = $this->track->levels()->create([
            'title' => 'Test Level',
            'difficulty' => 'beginner',
            'is_published' => true,
        ]);
        $this->module = $level->modules()->create([
            'title' => 'Test Module',
            'is_published' => true,
        ]);
        $this->lesson = $this->module->lessons()->create([
            'title' => 'Test Lesson',
            'content' => 'Test content',
            'is_published' => true,
        ]);
    }

    public function test_create_discussion_successfully()
    {
        $discussionData = [
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
            'title' => 'Test Discussion',
            'description' => 'A test discussion about the lesson',
            'is_active' => true,
        ];

        $discussion = $this->discussionService->createDiscussion($discussionData);

        $this->assertInstanceOf(Discussion::class, $discussion);
        $this->assertEquals('Test Discussion', $discussion->title);
        $this->assertEquals('A test discussion about the lesson', $discussion->description);
        $this->assertTrue($discussion->is_active);
        $this->assertEquals($this->lesson->id, $discussion->discussable_id);
        $this->assertEquals('App\Models\Lesson', $discussion->discussable_type);
    }

    public function test_create_discussion_validation_fails_with_invalid_data()
    {
        $this->expectException(ValidationException::class);

        $this->discussionService->createDiscussion([
            'discussable_type' => 'InvalidType',
            'discussable_id' => 999999, // Non-existent ID
            'title' => '', // Empty title
        ]);
    }

    public function test_create_post_successfully()
    {
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        $postData = [
            'discussion_id' => $discussion->id,
            'user_id' => $this->learner->id,
            'content' => 'This is a test post with some content.',
        ];

        $post = $this->discussionService->createPost($postData);

        $this->assertInstanceOf(DiscussionPost::class, $post);
        $this->assertEquals('This is a test post with some content.', $post->content);
        $this->assertEquals($this->learner->id, $post->user_id);
        $this->assertEquals($discussion->id, $post->discussion_id);
        $this->assertFalse($post->is_instructor_response); // Learner post
        $this->assertNull($post->parent_id); // Root post
    }

    public function test_create_post_marks_instructor_response()
    {
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        $postData = [
            'discussion_id' => $discussion->id,
            'user_id' => $this->instructor->id,
            'content' => 'This is an instructor response.',
        ];

        $post = $this->discussionService->createPost($postData);

        $this->assertTrue($post->is_instructor_response);
    }

    public function test_create_post_validation_fails_with_short_content()
    {
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->discussionService->createPost([
            'discussion_id' => $discussion->id,
            'user_id' => $this->learner->id,
            'content' => 'Short', // Too short (less than 10 characters)
        ]);
    }

    public function test_reply_to_post_successfully()
    {
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        $parentPost = DiscussionPost::factory()->create([
            'discussion_id' => $discussion->id,
            'user_id' => $this->learner->id,
        ]);

        $replyData = [
            'user_id' => $this->instructor->id,
            'content' => 'This is a reply to the original post.',
        ];

        $reply = $this->discussionService->replyToPost($parentPost, $replyData);

        $this->assertInstanceOf(DiscussionPost::class, $reply);
        $this->assertEquals($parentPost->id, $reply->parent_id);
        $this->assertEquals($discussion->id, $reply->discussion_id);
        $this->assertTrue($reply->is_instructor_response);
    }

    public function test_get_discussion_with_posts_organizes_qa_format()
    {
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        // Create a question post
        $questionPost = DiscussionPost::factory()->create([
            'discussion_id' => $discussion->id,
            'user_id' => $this->learner->id,
            'content' => 'I have a question about this lesson.',
        ]);

        // Create instructor reply
        $instructorReply = DiscussionPost::factory()->create([
            'discussion_id' => $discussion->id,
            'user_id' => $this->instructor->id,
            'parent_id' => $questionPost->id,
            'content' => 'Here is the answer to your question.',
            'is_instructor_response' => true,
        ]);

        // Create another question without instructor response
        $unansweredPost = DiscussionPost::factory()->create([
            'discussion_id' => $discussion->id,
            'user_id' => $this->learner->id,
            'content' => 'Another question that needs an answer.',
        ]);

        $result = $this->discussionService->getDiscussionWithPosts($discussion);

        $this->assertArrayHasKey('discussion', $result);
        $this->assertArrayHasKey('posts', $result);
        $this->assertArrayHasKey('total_posts', $result);
        $this->assertArrayHasKey('instructor_responses', $result);

        $this->assertEquals(3, $result['total_posts']);
        $this->assertEquals(1, $result['instructor_responses']);

        // Check Q&A organization - posts with instructor responses should come first
        $organizedPosts = $result['posts'];
        $this->assertCount(2, $organizedPosts); // 2 root posts

        $firstPost = $organizedPosts[0];
        $this->assertTrue($firstPost['has_instructor_response']);
        $this->assertEquals($questionPost->id, $firstPost['post']->id);
        $this->assertCount(1, $firstPost['sorted_replies']);
    }

    public function test_moderate_post_successfully()
    {
        $post = DiscussionPost::factory()->create([
            'user_id' => $this->learner->id,
            'is_moderated' => false,
        ]);

        $moderatedPost = $this->discussionService->moderatePost($post, true, 'Inappropriate content');

        $this->assertTrue($moderatedPost->is_moderated);
        $this->assertEquals('Inappropriate content', $moderatedPost->moderation_reason);
    }

    public function test_delete_post_soft_deletes()
    {
        $post = DiscussionPost::factory()->create([
            'user_id' => $this->learner->id,
        ]);

        $result = $this->discussionService->deletePost($post);

        $this->assertTrue($result);
        $this->assertSoftDeleted($post);
    }

    public function test_get_discussions_for_entity()
    {
        // Create discussions for the lesson
        $discussion1 = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
            'is_active' => true,
        ]);

        $discussion2 = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
            'is_active' => false,
        ]);

        // Create discussion for different entity
        Discussion::factory()->create([
            'discussable_type' => 'App\Models\Module',
            'discussable_id' => $this->module->id,
        ]);

        $discussions = $this->discussionService->getDiscussionsForEntity(
            'App\Models\Lesson',
            $this->lesson->id,
            true // active only
        );

        $this->assertCount(1, $discussions); // Only active discussion
        $this->assertEquals($discussion1->id, $discussions->first()->id);

        // Get all discussions (including inactive)
        $allDiscussions = $this->discussionService->getDiscussionsForEntity(
            'App\Models\Lesson',
            $this->lesson->id,
            false
        );

        $this->assertCount(2, $allDiscussions);
    }

    public function test_search_discussions()
    {
        $discussion1 = Discussion::factory()->create([
            'title' => 'JavaScript Basics',
            'description' => 'Discussion about JavaScript fundamentals',
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        $discussion2 = Discussion::factory()->create([
            'title' => 'PHP Advanced',
            'description' => 'Advanced PHP concepts',
            'discussable_type' => 'App\Models\Module',
            'discussable_id' => $this->module->id,
        ]);

        // Create posts with searchable content
        DiscussionPost::factory()->create([
            'discussion_id' => $discussion1->id,
            'content' => 'I need help with JavaScript arrays',
            'is_moderated' => false,
        ]);

        $results = $this->discussionService->searchDiscussions('JavaScript');

        $this->assertCount(1, $results);
        $this->assertEquals($discussion1->id, $results->first()->id);

        // Search with filters
        $filteredResults = $this->discussionService->searchDiscussions('PHP', [
            'discussable_type' => 'App\Models\Module',
        ]);

        $this->assertCount(1, $filteredResults);
        $this->assertEquals($discussion2->id, $filteredResults->first()->id);
    }

    public function test_get_discussion_statistics()
    {
        $discussion1 = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
            'is_active' => true,
        ]);

        $discussion2 = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
            'is_active' => false,
        ]);

        // Create posts
        DiscussionPost::factory()->count(3)->create([
            'discussion_id' => $discussion1->id,
            'is_instructor_response' => false,
            'is_moderated' => false,
        ]);

        DiscussionPost::factory()->create([
            'discussion_id' => $discussion1->id,
            'is_instructor_response' => true,
            'is_moderated' => false,
        ]);

        DiscussionPost::factory()->create([
            'discussion_id' => $discussion1->id,
            'is_instructor_response' => false,
            'is_moderated' => true,
        ]);

        $stats = $this->discussionService->getDiscussionStatistics(
            'App\Models\Lesson',
            $this->lesson->id
        );

        $this->assertEquals(2, $stats['total_discussions']);
        $this->assertEquals(1, $stats['active_discussions']);
        $this->assertEquals(5, $stats['total_posts']);
        $this->assertEquals(1, $stats['instructor_responses']);
        $this->assertEquals(1, $stats['moderated_posts']);
        $this->assertEquals(2.5, $stats['average_posts_per_discussion']);
    }

    public function test_process_post_content_sanitizes_html()
    {
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        $postData = [
            'discussion_id' => $discussion->id,
            'user_id' => $this->learner->id,
            'content' => 'This has <script>alert("xss")</script> and **bold** text with `code`.',
        ];

        $post = $this->discussionService->createPost($postData);

        // Script tags should be removed, but formatting should be preserved
        $this->assertStringNotContainsString('<script>', $post->content);
        $this->assertStringContainsString('<strong>bold</strong>', $post->content);
        $this->assertStringContainsString('<code>code</code>', $post->content);
    }

    public function test_requires_moderation_flags_inappropriate_content()
    {
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        $postData = [
            'discussion_id' => $discussion->id,
            'user_id' => $this->learner->id,
            'content' => 'This is spam content that should be flagged.',
        ];

        $post = $this->discussionService->createPost($postData);

        $this->assertTrue($post->is_moderated);
    }

    public function test_update_discussion_successfully()
    {
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
            'title' => 'Original Title',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $updatedDiscussion = $this->discussionService->updateDiscussion($discussion, $updateData);

        $this->assertEquals('Updated Title', $updatedDiscussion->title);
        $this->assertEquals('Updated description', $updatedDiscussion->description);
        $this->assertFalse($updatedDiscussion->is_active);
    }

    public function test_validate_parent_post_belongs_to_same_discussion()
    {
        $discussion1 = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Lesson',
            'discussable_id' => $this->lesson->id,
        ]);

        $discussion2 = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Module',
            'discussable_id' => $this->module->id,
        ]);

        $parentPost = DiscussionPost::factory()->create([
            'discussion_id' => $discussion2->id,
            'user_id' => $this->learner->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->discussionService->createPost([
            'discussion_id' => $discussion1->id,
            'user_id' => $this->instructor->id,
            'parent_id' => $parentPost->id,
            'content' => 'This should fail validation.',
        ]);
    }
}
