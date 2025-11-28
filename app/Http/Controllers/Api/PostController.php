<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Display a listing of published posts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Post::query()
            ->when(!$request->user(), fn($q) => $q->where('is_published', true))
            ->with(['user']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by tags
        if ($request->has('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }

        // Sort
        $sortBy = $request->get('sort', 'published_at');
        $sortOrder = $request->get('order', 'desc');

        if (in_array($sortBy, ['published_at', 'views_count', 'likes_count', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $posts = $query->paginate($request->get('per_page', 12));

        return PostResource::collection($posts);
    }

    /**
     * Store a newly created post.
     */
    public function store(Request $request): PostResource
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'cover' => 'nullable|string|url',
            'type' => 'required|in:article,carousel,video,stack_gallery',
            'media' => 'nullable|array',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['user_id'] = $request->user()->id;

        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Post::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Set published_at if not provided and is_published is true
        if (isset($validated['is_published']) && $validated['is_published'] && !isset($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $post = Post::create($validated);

        return new PostResource($post->load('user'));
    }

    /**
     * Display the specified post.
     */
    public function show(Request $request, string $slug): PostResource
    {
        $post = Post::where('slug', $slug)
            ->with(['user', 'media', 'playlists' => function($query) {
                $query->where('is_published', true);
            }])
            ->firstOrFail();

        // Check if published for non-authenticated users
        if (!$request->user() && !$post->is_published) {
            abort(404);
        }

        // Track view
        if (!$request->user() || $request->user()->id !== $post->user_id) {
            $post->incrementViews(
                $request->ip(),
                $request->userAgent(),
                $request->header('referer')
            );
        }

        // Check if user has liked this post
        $isLiked = false;
        if ($request->user()) {
            $isLiked = \App\Models\PostLike::where('post_id', $post->id)
                ->where('user_id', $request->user()->id)
                ->exists();
        } else {
            $isLiked = \App\Models\PostLike::where('post_id', $post->id)
                ->where('ip_address', $request->ip())
                ->exists();
        }

        $resource = new PostResource($post);
        $resource->additional(['is_liked' => $isLiked]);

        return $resource;
    }

    /**
     * Update the specified post.
     */
    public function update(Request $request, string $slug): PostResource
    {
        $post = Post::where('slug', $slug)->firstOrFail();

        // Authorization check
        if ($request->user()->id !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'cover' => 'nullable|string|url',
            'type' => 'sometimes|required|in:article,carousel,video,stack_gallery',
            'media' => 'nullable|array',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
        ]);

        // Update slug if title changed
        if (isset($validated['title']) && $validated['title'] !== $post->title) {
            $slug = Str::slug($validated['title']);
            $originalSlug = $slug;
            $counter = 1;
            while (Post::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        // Set published_at when publishing
        if (isset($validated['is_published']) && $validated['is_published'] && !$post->published_at && !isset($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $post->update($validated);

        return new PostResource($post->load('user'));
    }

    /**
     * Remove the specified post.
     */
    public function destroy(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();

        // Authorization check
        if ($request->user()->id !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully.'], 200);
    }

    /**
     * Like or unlike a post.
     */
    public function like(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        $ipAddress = $request->ip();

        // Check if already liked
        $likeQuery = \App\Models\PostLike::where('post_id', $post->id);

        if ($user) {
            $likeQuery->where('user_id', $user->id);
        } else {
            $likeQuery->where('ip_address', $ipAddress);
        }

        $existingLike = $likeQuery->first();

        if ($existingLike) {
            // Unlike
            $existingLike->delete();
            $post->decrement('likes_count');
            $isLiked = false;
        } else {
            // Like
            \App\Models\PostLike::create([
                'post_id' => $post->id,
                'user_id' => $user ? $user->id : null,
                'ip_address' => $ipAddress,
            ]);
            $post->increment('likes_count');
            $isLiked = true;
        }

        return response()->json([
            'message' => $isLiked ? 'Post liked successfully.' : 'Post unliked successfully.',
            'is_liked' => $isLiked,
            'likes_count' => $post->fresh()->likes_count,
        ], 200);
    }

    /**
     * Get recent posts.
     */
    public function recent(Request $request): AnonymousResourceCollection
    {
        $posts = Post::query()
            ->where('is_published', true)
            ->with(['user'])
            ->orderBy('published_at', 'desc')
            ->limit($request->get('limit', 6))
            ->get();

        return PostResource::collection($posts);
    }

    /**
     * Get popular posts.
     */
    public function popular(Request $request): AnonymousResourceCollection
    {
        $posts = Post::query()
            ->where('is_published', true)
            ->with(['user'])
            ->orderBy('views_count', 'desc')
            ->orderBy('likes_count', 'desc')
            ->limit($request->get('limit', 6))
            ->get();

        return PostResource::collection($posts);
    }
}
