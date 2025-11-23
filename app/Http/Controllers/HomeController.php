<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        // Get published playlists ordered by order column
        $playlists = Playlist::query()
            ->where('is_published', true)
            ->withCount('posts')
            ->orderBy('order')
            ->limit(6)
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

        // Get recent published posts
        $recentPosts = Post::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at', 'desc')
            ->limit(12)
            ->get()
            ->map(function ($post) {
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
                ];
            });

        return Inertia::render('Home', [
            'playlists' => $playlists,
            'recentPosts' => $recentPosts,
        ]);
    }
}
