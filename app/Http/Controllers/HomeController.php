<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('q');

        // Get published playlists ordered by order column
        $playlists = Playlist::query()
            ->where('is_published', true)
            ->withCount('posts')
            ->orderBy('order')
            ->limit(8)
            ->get()
            ->map(function ($playlist) {
                return [
                    'id' => $playlist->id,
                    'slug' => $playlist->slug,
                    'title' => $playlist->title,
                    'description' => $playlist->description,
                    'cover' => $playlist->cover,
                    'posts_count' => $playlist->posts_count,
                ];
            });

        // Get recent published posts with pagination and search
        $recentPosts = Post::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderBy('published_at', 'desc')
            ->paginate(8)
            ->withQueryString();

        // Get popular published posts with pagination and search
        $popularPosts = Post::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderBy('views_count', 'desc')
            ->paginate(8)
            ->withQueryString();

        // Get oldest published posts with pagination and search
        $oldestPosts = Post::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderBy('published_at', 'asc')
            ->paginate(8)
            ->withQueryString();

        // Get popular tags (extract from posts' tags array and count occurrences)
        $allTags = Post::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('tags')
            ->pluck('tags');

        $popularTags = collect($allTags)
            ->flatMap(function ($tagJson) {
                // Decode the JSON string to get the array of tags
                $decoded = json_decode($tagJson, true);
                return is_array($decoded) ? $decoded : [];
            })
            ->filter(function ($tag) {
                // Remove empty values
                return !empty($tag);
            })
            ->map(function ($tag) {
                // Normalize: trim whitespace and convert to lowercase for consistency
                return trim(strtolower($tag));
            })
            ->countBy() // Count occurrences of each tag
            ->sortDesc() // Sort by count (highest first)
            ->take(5) // Get top 5
            ->map(function ($count, $tag) {
                return [
                    'name' => $tag,
                    'count' => $count,
                ];
            })
            ->values();

        return Inertia::render('Home', [
            'playlists' => $playlists,
            'recentPosts' => $recentPosts,
            'popularPosts' => $popularPosts,
            'oldestPosts' => $oldestPosts,
            'popularTags' => $popularTags,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
