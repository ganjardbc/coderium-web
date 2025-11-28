<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchController extends Controller
{
    /**
     * Search for posts.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'type' => 'nullable|in:article,carousel,video,stack_gallery',
        ]);

        $query = Post::query()
            ->where('is_published', true)
            ->with(['user']);

        $searchTerm = $request->input('q');

        // Full-text search or LIKE search
        $query->where(function($q) use ($searchTerm) {
            $q->where('title', 'LIKE', "%{$searchTerm}%")
                ->orWhere('subtitle', 'LIKE', "%{$searchTerm}%")
                ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                ->orWhereJsonContains('tags', $searchTerm);
        });

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $posts = $query
            ->orderBy('published_at', 'desc')
            ->paginate($request->get('per_page', 12));

        return PostResource::collection($posts);
    }
}
