<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    /**
     * Display a listing of playlists.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $playlists = Playlist::query()
            ->withCount('posts')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('admin/playlists/Index', [
            'playlists' => $playlists,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new playlist.
     */
    public function create(): Response
    {
        $posts = \App\Models\Post::query()
            ->where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'slug', 'type', 'cover']);

        return Inertia::render('admin/playlists/Form', [
            'availablePosts' => $posts,
        ]);
    }

    /**
     * Store a newly created playlist.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover' => 'nullable|string|url',
            'is_published' => 'boolean',
            'order' => 'integer',
            'post_ids' => 'nullable|array',
            'post_ids.*' => 'integer|exists:posts,id',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['user_id'] = $request->user()->id;

        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Playlist::withTrashed()->where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Extract post IDs before creating playlist
        $postIds = $validated['post_ids'] ?? [];
        unset($validated['post_ids']);

        $playlist = Playlist::create($validated);

        // Attach posts if provided
        if (!empty($postIds)) {
            $syncData = [];
            foreach ($postIds as $index => $postId) {
                $syncData[$postId] = [
                    'order' => $index + 1,
                    'user_id' => $request->user()->id,
                ];
            }
            $playlist->posts()->sync($syncData);
        }

        return redirect()->route('admin.playlists.index')
            ->with('success', 'Playlist created successfully.');
    }

    /**
     * Show the form for editing the specified playlist.
     */
    public function edit(string $slug): Response
    {
        $playlist = Playlist::with('posts')->where('slug', $slug)->firstOrFail();

        $posts = \App\Models\Post::query()
            ->where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'slug', 'type', 'cover']);

        return Inertia::render('admin/playlists/Form', [
            'playlist' => $playlist,
            'availablePosts' => $posts,
        ]);
    }

    /**
     * Update the specified playlist.
     */
    public function update(Request $request, string $slug): RedirectResponse
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'cover' => 'nullable|string|url',
            'is_published' => 'boolean',
            'order' => 'integer',
            'post_ids' => 'nullable|array',
            'post_ids.*' => 'integer|exists:posts,id',
        ]);

        // Update slug if title changed
        if (isset($validated['title']) && $validated['title'] !== $playlist->title) {
            $slug = Str::slug($validated['title']);
            $originalSlug = $slug;
            $counter = 1;
            while (Playlist::withTrashed()->where('slug', $slug)->where('id', '!=', $playlist->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        // Extract post IDs before updating playlist
        $postIds = $validated['post_ids'] ?? null;
        unset($validated['post_ids']);

        $playlist->update($validated);

        // Sync posts if provided
        if ($postIds !== null) {
            $syncData = [];
            foreach ($postIds as $index => $postId) {
                $syncData[$postId] = [
                    'order' => $index + 1,
                    'user_id' => $request->user()->id,
                ];
            }
            $playlist->posts()->sync($syncData);
        }

        return redirect()->route('admin.playlists.index')
            ->with('success', 'Playlist updated successfully.');
    }

    /**
     * Remove the specified playlist.
     */
    public function destroy(string $slug): RedirectResponse
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();
        $playlist->delete();

        return redirect()->route('admin.playlists.index')
            ->with('success', 'Playlist deleted successfully.');
    }
}
