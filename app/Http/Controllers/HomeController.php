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
            ->with(['user', 'media'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderBy('published_at', 'desc')
            ->paginate(8)
            ->withQueryString()
            ->through(function ($post) {
                // Get media from relationship (uploaded files)
                $mediaItems = $post->media()->get()->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->url,
                        'type' => $media->type,
                    ];
                })->values();

                // If no media from relationship, check if carousel/video posts have media in JSON field
                if ($mediaItems->isEmpty() && $post->type === 'carousel' && !empty($post->getAttributes()['media'])) {
                    // For backward compatibility: convert JSON media array to expected format
                    $jsonMedia = $post->getAttributes()['media'];
                    if (is_array($jsonMedia)) {
                        $mediaItems = collect($jsonMedia)->map(function ($url, $index) {
                            return [
                                'id' => $index,
                                'url' => $url,
                                'type' => 'image',
                            ];
                        })->values();
                    }
                }

                return [
                    'id' => $post->id,
                    'slug' => $post->slug,
                    'title' => $post->title,
                    'subtitle' => $post->subtitle,
                    'cover' => $post->cover,
                    'type' => $post->type,
                    'tags' => $post->tags ?? [],
                    'views_count' => $post->views_count,
                    'likes_count' => $post->likes_count,
                    'published_at' => $post->published_at->toISOString(),
                    'media' => $mediaItems->toArray(),
                    'user' => [
                        'name' => $post->user->name,
                    ],
                ];
            });

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
            'popularTags' => $popularTags,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
