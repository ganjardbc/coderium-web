<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    public function index(): Response
    {
        $playlists = Playlist::query()
            ->where('is_published', true)
            ->withCount('posts')
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return Inertia::render('Playlists', [
            'playlists' => $playlists,
        ]);
    }

    public function show(string $slug): Response
    {
        $playlist = Playlist::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with(['user:id,name', 'posts' => function ($query) {
                $query->where('is_published', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->orderBy('playlist_post.order');
            }])
            ->firstOrFail();

        $playlistData = [
            'id' => $playlist->id,
            'slug' => $playlist->slug,
            'title' => $playlist->title,
            'description' => $playlist->description,
            'cover' => $playlist->cover,
            'user' => [
                'id' => $playlist->user->id,
                'name' => $playlist->user->name,
            ],
            'posts_count' => $playlist->posts->count(),
            'posts' => $playlist->posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'slug' => $post->slug,
                    'title' => $post->title,
                    'subtitle' => $post->subtitle,
                    'cover' => $post->cover,
                    'type' => $post->type,
                    'tags' => $post->tags,
                    'views_count' => $post->views_count,
                    'likes_count' => $post->likes_count,
                    'published_at' => $post->published_at->toISOString(),
                    'order' => $post->pivot->order,
                ];
            }),
        ];

        return Inertia::render('PlaylistDetail', [
            'playlist' => $playlistData,
        ]);
    }
}
