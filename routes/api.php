<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TrackController;
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

    // Public Classroom Routes
    Route::prefix('classroom')->group(function () {
        // Public tracks (published only)
        Route::get('/tracks', [TrackController::class, 'index']);
        Route::get('/tracks/{slug}', [TrackController::class, 'show']);

        // Public content hierarchy (for free tracks)
        Route::get('/tracks/{slug}/hierarchy', [ContentController::class, 'trackHierarchy']);
        Route::get('/tracks/{slug}/levels', [ContentController::class, 'trackLevels']);
        Route::get('/levels/{level}/modules', [ContentController::class, 'levelModules']);
        Route::get('/modules/{module}/lessons', [ContentController::class, 'moduleLessons']);
        Route::get('/lessons/{lesson}', [ContentController::class, 'showLesson']);
        Route::get('/lessons/{lesson}/navigation', [ContentController::class, 'lessonNavigation']);

        // Public assessment access (for free tracks)
        Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
        Route::get('/assessments/{assessment}/can-take', [AssessmentController::class, 'canTake']);
        Route::get('/content/assessments', [AssessmentController::class, 'contentAssessments']);
    });

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

        // Classroom Routes (Authenticated)
        Route::prefix('classroom')->group(function () {

            // Track Management
            Route::post('/tracks', [TrackController::class, 'store']);
            Route::put('/tracks/{slug}', [TrackController::class, 'update']);
            Route::delete('/tracks/{slug}', [TrackController::class, 'destroy']);
            Route::post('/tracks/{slug}/enroll', [TrackController::class, 'enroll']);
            Route::delete('/tracks/{slug}/enroll', [TrackController::class, 'unenroll']);
            Route::post('/tracks/{slug}/publish', [TrackController::class, 'publish']);
            Route::get('/tracks/{slug}/enrollment-stats', [TrackController::class, 'enrollmentStats']);

            // Content Management & Progress
            Route::post('/lessons/{lesson}/complete', [ContentController::class, 'completeLesson']);
            Route::post('/lessons/{lesson}/time', [ContentController::class, 'updateLessonTime']);
            Route::get('/tracks/{slug}/progress', [ContentController::class, 'trackProgress']);

            // Assessment Management
            Route::post('/assessments/{assessment}/submit', [AssessmentController::class, 'submit']);
            Route::get('/assessments/{assessment}/results', [AssessmentController::class, 'results']);
            Route::get('/assessments/{assessment}/attempts/{attempt}', [AssessmentController::class, 'attemptDetails']);
            Route::post('/assessments/{assessment}/start', [AssessmentController::class, 'startAttempt']);
            Route::get('/content/access-check', [AssessmentController::class, 'checkContentAccess']);
            Route::get('/assessments/progress-summary', [AssessmentController::class, 'progressSummary']);
        });
    });
});
