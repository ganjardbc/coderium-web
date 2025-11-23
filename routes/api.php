<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:web');

/*
|--------------------------------------------------------------------------
| Public API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Public Playlists
    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::get('/playlists/{slug}', [PlaylistController::class, 'show']);

    // Public Posts
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/recent', [PostController::class, 'recent']);
    Route::get('/posts/popular', [PostController::class, 'popular']);
    Route::get('/posts/{slug}', [PostController::class, 'show']);
    Route::post('/posts/{slug}/like', [PostController::class, 'like']);

    // Search
    Route::get('/search', SearchController::class);

    /*
    |--------------------------------------------------------------------------
    | Protected API Routes (v1) - Requires Authentication
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:web')->group(function () {

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index']);

        // Media Management
        Route::get('/media', [MediaController::class, 'index']);
        Route::post('/media/upload', [MediaController::class, 'upload']);
        Route::post('/media/upload-multiple', [MediaController::class, 'uploadMultiple']);
        Route::get('/media/{id}', [MediaController::class, 'show']);
        Route::put('/media/{id}', [MediaController::class, 'update']);
        Route::delete('/media/{id}', [MediaController::class, 'destroy']);

        // Playlist Management
        Route::post('/playlists', [PlaylistController::class, 'store']);
        Route::put('/playlists/{slug}', [PlaylistController::class, 'update']);
        Route::delete('/playlists/{slug}', [PlaylistController::class, 'destroy']);
        Route::post('/playlists/{slug}/posts', [PlaylistController::class, 'attachPosts']);
        Route::delete('/playlists/{slug}/posts', [PlaylistController::class, 'detachPosts']);

        // Post Management
        Route::post('/posts', [PostController::class, 'store']);
        Route::put('/posts/{slug}', [PostController::class, 'update']);
        Route::delete('/posts/{slug}', [PostController::class, 'destroy']);
    });
});
