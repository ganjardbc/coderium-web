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
        $search = $request->input('search');

        // Get published playlists ordered by order column
        $playlists = Playlist::query()
            ->where('is_published', true)
            ->withCount('posts')
            ->orderBy('order')
            ->limit(4)
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

        return Inertia::render('Home', [
            'playlists' => $playlists,
            'recentPosts' => $recentPosts,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
