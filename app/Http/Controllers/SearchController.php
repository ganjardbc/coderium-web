<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->input('q', '');
        $type = $request->input('type', 'all');
        $sortBy = $request->input('sort', 'recent');
        $perPage = 12;

        $postsQuery = Post::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        // Filter by search query
        if (!empty($query)) {
            $postsQuery->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('subtitle', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%")
                    ->orWhere('tags', 'like', "%{$query}%");
            });
        }

        // Filter by type
        if ($type !== 'all') {
            $postsQuery->where('type', $type);
        }

        // Sort results
        switch ($sortBy) {
            case 'popular':
                $postsQuery->orderBy('views_count', 'desc');
                break;
            case 'likes':
                $postsQuery->orderBy('likes_count', 'desc');
                break;
            case 'oldest':
                $postsQuery->orderBy('published_at', 'asc');
                break;
            case 'recent':
            default:
                $postsQuery->orderBy('published_at', 'desc');
                break;
        }

        $posts = $postsQuery->paginate($perPage)->through(function ($post) {
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
            ];
        });

        // Get total counts by type
        $counts = [
            'all' => Post::where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->count(),
            'article' => Post::where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->where('type', 'article')
                ->count(),
            'carousel' => Post::where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->where('type', 'carousel')
                ->count(),
            'video' => Post::where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->where('type', 'video')
                ->count(),
        ];

        return Inertia::render('PostLists', [
            'posts' => $posts,
            'counts' => $counts,
            'filters' => [
                'query' => $query,
                'type' => $type,
                'sortBy' => $sortBy,
            ],
        ]);
    }
}
