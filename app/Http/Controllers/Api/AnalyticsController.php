<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Playlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get analytics dashboard data.
     */
    public function index(): JsonResponse
    {
        $totalPosts = Post::count();
        $totalPlaylists = Playlist::count();
        $totalViews = Post::sum('views_count');
        $totalLikes = Post::sum('likes_count');

        // Posts by type
        $postsByType = Post::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        // Top 5 most viewed posts
        $topViewedPosts = Post::select('id', 'title', 'slug', 'views_count', 'type')
            ->orderBy('views_count', 'desc')
            ->limit(6)
            ->get();

        // Top 5 most liked posts
        $topLikedPosts = Post::select('id', 'title', 'slug', 'likes_count', 'type')
            ->orderBy('likes_count', 'desc')
            ->limit(6)
            ->get();

        // Recent views (last 7 days)
        $recentViews = DB::table('post_views')
            ->select(DB::raw('DATE(viewed_at) as date'), DB::raw('count(*) as count'))
            ->where('viewed_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Posts created per month (last 6 months)
        $postsPerMonth = Post::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'overview' => [
                'total_posts' => $totalPosts,
                'total_playlists' => $totalPlaylists,
                'total_views' => $totalViews,
                'total_likes' => $totalLikes,
            ],
            'posts_by_type' => $postsByType,
            'top_viewed_posts' => $topViewedPosts,
            'top_liked_posts' => $topLikedPosts,
            'recent_views' => $recentViews,
            'posts_per_month' => $postsPerMonth,
        ]);
    }
}
