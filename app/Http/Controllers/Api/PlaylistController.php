<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class PlaylistController extends Controller
{
    /**
     * Display a listing of published playlists.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $playlists = Playlist::query()
            ->when(!$request->user(), fn($query) => $query->where('is_published', true))
            ->with(['user'])
            ->withCount('posts')
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return PlaylistResource::collection($playlists);
    }

    /**
     * Store a newly created playlist.
     */
    public function store(Request $request): PlaylistResource
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover' => 'nullable|string|url',
            'is_published' => 'boolean',
            'order' => 'integer',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['user_id'] = $request->user()->id;

        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Playlist::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $playlist = Playlist::create($validated);

        return new PlaylistResource($playlist->load('user'));
    }

    /**
     * Display the specified playlist.
     */
    public function show(string $slug): PlaylistResource
    {
        $playlist = Playlist::where('slug', $slug)
            ->with(['user', 'posts' => function($query) {
                $query->where('is_published', true)
                    ->orderBy('playlist_post.order');
            }])
            ->firstOrFail();

        // Check if published for non-authenticated users
        if (!request()->user() && !$playlist->is_published) {
            abort(404);
        }

        return new PlaylistResource($playlist);
    }

    /**
     * Update the specified playlist.
     */
    public function update(Request $request, string $slug): PlaylistResource
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();

        // Authorization check
        if ($request->user()->id !== $playlist->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'cover' => 'nullable|string|url',
            'is_published' => 'boolean',
            'order' => 'integer',
        ]);

        // Update slug if title changed
        if (isset($validated['title']) && $validated['title'] !== $playlist->title) {
            $slug = Str::slug($validated['title']);
            $originalSlug = $slug;
            $counter = 1;
            while (Playlist::where('slug', $slug)->where('id', '!=', $playlist->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $playlist->update($validated);

        return new PlaylistResource($playlist->load('user'));
    }

    /**
     * Remove the specified playlist.
     */
    public function destroy(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();

        // Authorization check
        if ($request->user()->id !== $playlist->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $playlist->delete();

        return response()->json(['message' => 'Playlist deleted successfully.'], 200);
    }

    /**
     * Attach posts to playlist.
     */
    public function attachPosts(Request $request, string $slug): PlaylistResource
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();

        // Authorization check
        if ($request->user()->id !== $playlist->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'post_ids' => 'required|array',
            'post_ids.*' => 'exists:posts,id',
        ]);

        foreach ($validated['post_ids'] as $index => $postId) {
            if (!$playlist->posts()->where('post_id', $postId)->exists()) {
                $playlist->posts()->attach($postId, [
                    'user_id' => $request->user()->id,
                    'order' => $index + 1,
                ]);
            }
        }

        return new PlaylistResource($playlist->load(['posts', 'user']));
    }

    /**
     * Detach posts from playlist.
     */
    public function detachPosts(Request $request, string $slug): PlaylistResource
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();

        // Authorization check
        if ($request->user()->id !== $playlist->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'post_ids' => 'required|array',
            'post_ids.*' => 'exists:posts,id',
        ]);

        $playlist->posts()->detach($validated['post_ids']);

        return new PlaylistResource($playlist->load(['posts', 'user']));
    }
}
