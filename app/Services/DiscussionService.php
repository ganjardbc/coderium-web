<?php

namespace App\Services;

use App\Models\Discussion;
use App\Models\DiscussionPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class DiscussionService
{
    /**
     * Create a new discussion thread.
     *
     * @param array $data
     * @return Discussion
     * @throws ValidationException
     */
    public function createDiscussion(array $data): Discussion
    {
        $this->validateDiscussionData($data);

        return DB::transaction(function () use ($data) {
            return Discussion::create($data);
        });
    }

    /**
     * Create a new discussion post.
     *
     * @param array $data
     * @return DiscussionPost
     * @throws ValidationException
     */
    public function createPost(array $data): DiscussionPost
    {
        $this->validatePostData($data);

        return DB::transaction(function () use ($data) {
            // Process content for moderation
            $data['content'] = $this->processPostContent($data['content']);

            // Check if user is instructor
            $user = User::find($data['user_id']);
            $data['is_instructor_response'] = $user && $user->role === 'instructor';

            // Apply basic content moderation
            $data['is_moderated'] = $this->requiresModeration($data['content']);

            $post = DiscussionPost::create($data);

            // Send notifications for new posts
            $this->sendPostNotifications($post);

            return $post;
        });
    }

    /**
     * Reply to a discussion post.
     *
     * @param DiscussionPost $parentPost
     * @param array $data
     * @return DiscussionPost
     * @throws ValidationException
     */
    public function replyToPost(DiscussionPost $parentPost, array $data): DiscussionPost
    {
        $data['discussion_id'] = $parentPost->discussion_id;
        $data['parent_id'] = $parentPost->id;

        return $this->createPost($data);
    }

    /**
     * Get discussion with posts organized in Q&A format.
     *
     * @param Discussion $discussion
     * @param bool $includeModerated
     * @return array
     */
    public function getDiscussionWithPosts(Discussion $discussion, bool $includeModerated = false): array
    {
        $query = $discussion->rootPosts()->with(['user', 'replies.user']);

        if (!$includeModerated) {
            $query->where('is_moderated', false);
        }

        $posts = $query->get();

        // Organize posts in Q&A format
        $organizedPosts = $this->organizePostsInQAFormat($posts);

        return [
            'discussion' => $discussion,
            'posts' => $organizedPosts,
            'total_posts' => $discussion->posts()->count(),
            'instructor_responses' => $discussion->posts()->where('is_instructor_response', true)->count(),
        ];
    }

    /**
     * Update discussion settings.
     *
     * @param Discussion $discussion
     * @param array $data
     * @return Discussion
     * @throws ValidationException
     */
    public function updateDiscussion(Discussion $discussion, array $data): Discussion
    {
        $this->validateDiscussionData($data, $discussion->id);

        return DB::transaction(function () use ($discussion, $data) {
            $discussion->update($data);
            return $discussion->fresh();
        });
    }

    /**
     * Moderate a discussion post.
     *
     * @param DiscussionPost $post
     * @param bool $isModerated
     * @param string|null $reason
     * @return DiscussionPost
     */
    public function moderatePost(DiscussionPost $post, bool $isModerated, ?string $reason = null): DiscussionPost
    {
        return DB::transaction(function () use ($post, $isModerated, $reason) {
            $post->update([
                'is_moderated' => $isModerated,
                'moderation_reason' => $reason,
            ]);

            // Notify user if post is moderated
            if ($isModerated && $post->user) {
                $this->sendModerationNotification($post, $reason);
            }

            return $post->fresh();
        });
    }

    /**
     * Delete a discussion post.
     *
     * @param DiscussionPost $post
     * @return bool
     */
    public function deletePost(DiscussionPost $post): bool
    {
        return DB::transaction(function () use ($post) {
            // Soft delete to preserve discussion context
            return $post->delete();
        });
    }

    /**
     * Get discussions for a discussable entity.
     *
     * @param string $discussableType
     * @param int $discussableId
     * @param bool $activeOnly
     * @return Collection
     */
    public function getDiscussionsForEntity(string $discussableType, int $discussableId, bool $activeOnly = true): Collection
    {
        $query = Discussion::where('discussable_type', $discussableType)
            ->where('discussable_id', $discussableId)
            ->with(['posts' => function ($query) {
                $query->where('is_moderated', false)->limit(3);
            }]);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Search discussions and posts.
     *
     * @param string $query
     * @param array $filters
     * @return Collection
     */
    public function searchDiscussions(string $query, array $filters = []): Collection
    {
        $searchQuery = Discussion::query()
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhereHas('posts', function ($postQuery) use ($query) {
                      $postQuery->where('content', 'LIKE', "%{$query}%")
                               ->where('is_moderated', false);
                  });
            });

        // Apply filters
        if (isset($filters['discussable_type'])) {
            $searchQuery->where('discussable_type', $filters['discussable_type']);
        }

        if (isset($filters['has_instructor_response'])) {
            $searchQuery->whereHas('posts', function ($q) {
                $q->where('is_instructor_response', true);
            });
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $searchQuery->where('is_active', true);
        }

        return $searchQuery->with(['posts' => function ($query) {
            $query->where('is_moderated', false)->limit(2);
        }])->get();
    }

    /**
     * Get discussion statistics.
     *
     * @param string|null $discussableType
     * @param int|null $discussableId
     * @return array
     */
    public function getDiscussionStatistics(?string $discussableType = null, ?int $discussableId = null): array
    {
        $query = Discussion::query();

        if ($discussableType && $discussableId) {
            $query->where('discussable_type', $discussableType)
                  ->where('discussable_id', $discussableId);
        }

        $discussions = $query->with('posts')->get();

        return [
            'total_discussions' => $discussions->count(),
            'active_discussions' => $discussions->where('is_active', true)->count(),
            'total_posts' => $discussions->sum(fn($d) => $d->posts->count()),
            'instructor_responses' => $discussions->sum(fn($d) => $d->posts->where('is_instructor_response', true)->count()),
            'moderated_posts' => $discussions->sum(fn($d) => $d->posts->where('is_moderated', true)->count()),
            'average_posts_per_discussion' => $discussions->count() > 0
                ? round($discussions->sum(fn($d) => $d->posts->count()) / $discussions->count(), 2)
                : 0,
        ];
    }

    /**
     * Validate discussion data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @throws ValidationException
     */
    private function validateDiscussionData(array $data, ?int $excludeId = null): void
    {
        $rules = [
            'discussable_type' => 'sometimes|required|string|in:App\Models\Lesson,App\Models\Module,App\Models\Track',
            'discussable_id' => 'sometimes|required|integer|min:1',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];

        // For creation, make certain fields required
        if ($excludeId === null) {
            $rules['discussable_type'] = 'required|string|in:App\Models\Lesson,App\Models\Module,App\Models\Track';
            $rules['discussable_id'] = 'required|integer|min:1';
            $rules['title'] = 'required|string|max:255';
        }

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate that discussable entity exists
        if (isset($data['discussable_type']) && isset($data['discussable_id'])) {
            $this->validateDiscussableEntity($data['discussable_type'], $data['discussable_id']);
        }
    }

    /**
     * Validate post data.
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validatePostData(array $data): void
    {
        $rules = [
            'discussion_id' => 'required|exists:discussions,id',
            'user_id' => 'required|exists:users,id',
            'parent_id' => 'nullable|exists:discussion_posts,id',
            'content' => 'required|string|min:10|max:5000',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate parent post belongs to same discussion
        if (isset($data['parent_id']) && isset($data['discussion_id'])) {
            $parentPost = DiscussionPost::find($data['parent_id']);
            if ($parentPost && $parentPost->discussion_id != $data['discussion_id']) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent post must belong to the same discussion.',
                ]);
            }
        }
    }

    /**
     * Validate discussable entity exists.
     *
     * @param string $type
     * @param int $id
     * @throws ValidationException
     */
    private function validateDiscussableEntity(string $type, int $id): void
    {
        $modelClass = $type;

        if (!class_exists($modelClass)) {
            throw ValidationException::withMessages([
                'discussable_type' => 'Invalid discussable type.',
            ]);
        }

        if (!$modelClass::find($id)) {
            throw ValidationException::withMessages([
                'discussable_id' => 'Discussable entity not found.',
            ]);
        }
    }

    /**
     * Process post content for safety and formatting.
     *
     * @param string $content
     * @return string
     */
    private function processPostContent(string $content): string
    {
        // Sanitize HTML content
        $allowedTags = '<p><br><strong><b><em><i><u><a><code><pre><blockquote><ul><ol><li>';
        $content = strip_tags($content, $allowedTags);

        // Process code snippets
        $content = preg_replace(
            '/```([a-zA-Z]*)\n(.*?)\n```/s',
            '<pre><code class="language-$1">$2</code></pre>',
            $content
        );

        // Process inline code
        $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);

        // Process basic markdown
        $content = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $content);

        return trim($content);
    }

    /**
     * Check if content requires moderation.
     *
     * @param string $content
     * @return bool
     */
    private function requiresModeration(string $content): bool
    {
        // Basic content moderation rules
        $flaggedWords = [
            'spam', 'scam', 'inappropriate', 'offensive',
            // Add more flagged words as needed
        ];

        $lowerContent = strtolower($content);

        foreach ($flaggedWords as $word) {
            if (str_contains($lowerContent, $word)) {
                return true;
            }
        }

        // Check for excessive caps (more than 50% uppercase)
        $uppercaseCount = preg_match_all('/[A-Z]/', $content);
        $totalLetters = preg_match_all('/[A-Za-z]/', $content);

        if ($totalLetters > 0 && ($uppercaseCount / $totalLetters) > 0.5) {
            return true;
        }

        // Check for excessive special characters or numbers (potential spam)
        $specialCount = preg_match_all('/[^A-Za-z0-9\s]/', $content);
        if ($specialCount > strlen($content) * 0.3) {
            return true;
        }

        return false;
    }

    /**
     * Organize posts in Q&A format.
     *
     * @param Collection $posts
     * @return array
     */
    private function organizePostsInQAFormat(Collection $posts): array
    {
        $organized = [];

        foreach ($posts as $post) {
            $postData = [
                'post' => $post,
                'replies' => $post->replies->where('is_moderated', false),
                'instructor_replies' => $post->replies->where('is_instructor_response', true)->where('is_moderated', false),
                'has_instructor_response' => $post->replies->where('is_instructor_response', true)->where('is_moderated', false)->isNotEmpty(),
            ];

            // Sort replies: instructor responses first, then chronological
            $sortedReplies = $post->replies->where('is_moderated', false)->sortBy(function ($reply) {
                return [$reply->is_instructor_response ? 0 : 1, $reply->created_at];
            });

            $postData['sorted_replies'] = $sortedReplies->values();
            $organized[] = $postData;
        }

        // Sort posts: those with instructor responses first, then by creation date
        usort($organized, function ($a, $b) {
            if ($a['has_instructor_response'] && !$b['has_instructor_response']) {
                return -1;
            }
            if (!$a['has_instructor_response'] && $b['has_instructor_response']) {
                return 1;
            }
            return $a['post']->created_at <=> $b['post']->created_at;
        });

        return $organized;
    }

    /**
     * Send notifications for new posts.
     *
     * @param DiscussionPost $post
     * @return void
     */
    private function sendPostNotifications(DiscussionPost $post): void
    {
        // Get users to notify (discussion participants, instructors, etc.)
        $usersToNotify = collect();

        // Notify discussion participants
        $discussionParticipants = User::whereHas('discussionPosts', function ($query) use ($post) {
            $query->where('discussion_id', $post->discussion_id)
                  ->where('user_id', '!=', $post->user_id);
        })->get();

        $usersToNotify = $usersToNotify->merge($discussionParticipants);

        // Notify instructors if this is a new question
        if ($post->isRootPost()) {
            $instructors = User::where('role', 'instructor')->get();
            $usersToNotify = $usersToNotify->merge($instructors);
        }

        // Notify parent post author if this is a reply
        if ($post->parent_id && $post->parent->user_id != $post->user_id) {
            $usersToNotify->push($post->parent->user);
        }

        // Remove duplicates and the post author
        $usersToNotify = $usersToNotify->unique('id')->reject(function ($user) use ($post) {
            return $user->id === $post->user_id;
        });

        // Send notifications (implement notification classes as needed)
        // Notification::send($usersToNotify, new NewDiscussionPostNotification($post));
    }

    /**
     * Send moderation notification to user.
     *
     * @param DiscussionPost $post
     * @param string|null $reason
     * @return void
     */
    private function sendModerationNotification(DiscussionPost $post, ?string $reason = null): void
    {
        // Send notification to post author about moderation
        // Notification::send($post->user, new PostModerationNotification($post, $reason));
    }
}
