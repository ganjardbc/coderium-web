<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(string $slug): Response
    {
        $post = Post::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['user', 'media'])
            ->firstOrFail();

        // Increment views count
        $post->incrementViews(
            request()->ip(),
            request()->userAgent(),
            request()->headers->get('referer')
        );

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

        // Check if user has liked this post
        $isLiked = false;
        if (auth()->check()) {
            $isLiked = \App\Models\PostLike::where('post_id', $post->id)
                ->where('user_id', auth()->id())
                ->exists();
        } else {
            $isLiked = \App\Models\PostLike::where('post_id', $post->id)
                ->where('ip_address', request()->ip())
                ->exists();
        }

        $relatedPosts = collect();
        $relatedLimit = 4;

        // Fetch related posts randomly based on shared tags and title keywords
        if (!empty($post->tags)) {
            $tags = is_array($post->tags) ? $post->tags : [];
            $relatedPosts = Post::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->where('id', '!=', $post->id)
                ->where(function ($query) use ($tags, $post) {
                    foreach ($tags as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                    // Also match title keywords
                    $titleKeywords = preg_split('/\s+/', $post->title);
                    foreach ($titleKeywords as $keyword) {
                        $query->orWhere('title', 'like', "%{$keyword}%");
                    }
                })
                ->inRandomOrder()
                ->orderBy('published_at', 'desc')
                ->limit($relatedLimit)
                ->get();
        }

        // show random posts if related posts are less than limit
        if ($relatedPosts->count() < $relatedLimit) {
            $additionalPosts = Post::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->where('id', '!=', $post->id)
                ->whereNotIn('id', $relatedPosts->pluck('id')->toArray())
                ->inRandomOrder()
                ->limit($relatedLimit - $relatedPosts->count())
                ->get();

            $relatedPosts = $relatedPosts->merge($additionalPosts);
        }

        return Inertia::render('PostDetail', [
            'post' => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'subtitle' => $post->subtitle,
                'content' => $post->content,
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
            ],
            'isLiked' => $isLiked,
            'relatedPosts' => $relatedPosts->toArray(),
        ]);
    }
}
