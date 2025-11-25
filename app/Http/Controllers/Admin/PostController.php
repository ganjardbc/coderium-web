<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Display a listing of posts.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');

        $posts = Post::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/posts/Index', [
            'posts' => $posts,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new post.
     */
    public function create(): Response
    {
        return Inertia::render('admin/posts/Form');
    }

    /**
     * Store a newly created post.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'cover' => 'nullable|string|url',
            'type' => 'required|in:article,carousel,video',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'integer|exists:media,id',
            'is_published' => 'boolean',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['user_id'] = $request->user()->id;

        // Encode tags as JSON
        if (isset($validated['tags'])) {
            $validated['tags'] = json_encode($validated['tags']);
        }

        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Post::withTrashed()->where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Set published_at if publishing
        if (isset($validated['is_published']) && $validated['is_published']) {
            $validated['published_at'] = now();
        }

        // Extract media IDs before creating post
        $mediaIds = $validated['media_ids'] ?? [];
        unset($validated['media_ids']);

        $post = Post::create($validated);

        // Attach media files if provided
        if (!empty($mediaIds)) {
            foreach ($mediaIds as $index => $mediaId) {
                $post->media()->attach($mediaId, [
                    'tag' => $validated['type'] === 'carousel' ? 'carousel' : ($validated['type'] === 'video' ? 'video' : null),
                    'order' => $index + 1,
                ]);
            }
        }

        return redirect()->route('admin.posts.index')
            ->with('success', 'Post created successfully.');
    }

    /**
     * Show the form for editing the specified post.
     */
    public function edit(string $slug): Response
    {
        $post = Post::with('media')->where('slug', $slug)->firstOrFail();

        // Parse tags from JSON string to array
        $postData = $post->toArray();
        if (isset($postData['tags']) && is_string($postData['tags'])) {
            $postData['tags'] = json_decode($postData['tags'], true) ?? [];
        }

        return Inertia::render('admin/posts/Form', [
            'post' => $postData,
        ]);
    }

    /**
     * Update the specified post.
     */
    public function update(Request $request, string $slug): RedirectResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'cover' => 'nullable|string|url',
            'type' => 'sometimes|required|in:article,carousel,video',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'integer|exists:media,id',
            'is_published' => 'boolean',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
        ]);

        // Update slug if title changed
        if (isset($validated['title']) && $validated['title'] !== $post->title) {
            $slug = Str::slug($validated['title']);
            $originalSlug = $slug;
            $counter = 1;
            while (Post::withTrashed()->where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        // Set published_at when publishing
        if (isset($validated['is_published']) && $validated['is_published'] && !$post->published_at) {
            $validated['published_at'] = now();
        }

        // Extract media IDs before updating post
        $mediaIds = $validated['media_ids'] ?? null;
        unset($validated['media_ids']);

        // Encode tags as JSON
        if (isset($validated['tags'])) {
            $validated['tags'] = json_encode($validated['tags']);
        }

        $post->update($validated);

        // Sync media files if provided
        if ($mediaIds !== null) {
            $syncData = [];
            foreach ($mediaIds as $index => $mediaId) {
                $syncData[$mediaId] = [
                    'tag' => $validated['type'] === 'carousel' ? 'carousel' : ($validated['type'] === 'video' ? 'video' : null),
                    'order' => $index + 1,
                ];
            }
            $post->media()->sync($syncData);
        }

        return redirect()->route('admin.posts.index')
            ->with('success', 'Post updated successfully.');
    }

    /**
     * Remove the specified post.
     */
    public function destroy(string $slug): RedirectResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        $post->delete();

        return redirect()->route('admin.posts.index')
            ->with('success', 'Post deleted successfully.');
    }
}
