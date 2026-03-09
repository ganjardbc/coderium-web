<?php

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AssignmentTargetController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\CourseTemplateController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TrackController;
use App\Models\CourseEnrollment;
use App\Models\LessonProgress;
use App\Models\TrackEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:web');

/*
|--------------------------------------------------------------------------
| Direct API Routes (without v1 prefix for backward compatibility)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:web')->group(function () {
    // User-specific endpoints
    Route::put('/user/profile', function (Request $request) {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'bio' => 'sometimes|nullable|string',
            'avatar' => 'sometimes|nullable|string',
        ]);

        $user->update($validated);
        return response()->json($user);
    });

    Route::get('/user/track-enrollments', function (Request $request) {
        $enrollments = TrackEnrollment::with('track')
            ->where('user_id', $request->user()->id)
            ->get();
        return response()->json($enrollments);
    });

    Route::get('/user/preferences', function (Request $request) {
        // Return default preferences for now - you can expand this
        return response()->json([
            'theme' => 'light',
            'notifications' => true,
            'language' => 'en',
        ]);
    });

    Route::put('/user/preferences', function (Request $request) {
        $validated = $request->validate([
            'theme' => 'sometimes|in:light,dark',
            'notifications' => 'sometimes|boolean',
            'language' => 'sometimes|string|max:5',
        ]);

        // Store preferences logic here - you might want to create a UserPreferences model
        return response()->json($validated);
    });

    Route::get('/user/analytics', function (Request $request) {
        $userId = $request->user()->id;

        // Basic analytics - expand based on your needs
        $analytics = [
            'total_courses' => CourseEnrollment::where('user_id', $userId)->count(),
            'total_tracks' => TrackEnrollment::where('user_id', $userId)->count(),
            'completed_lessons' => LessonProgress::where('user_id', $userId)->where('completed', true)->count(),
            'total_time_spent' => LessonProgress::where('user_id', $userId)->sum('time_spent') ?? 0,
        ];

        return response()->json($analytics);
    });

    // Direct API routes that frontend expects without v1 prefix
    Route::get('/modules', [ModuleController::class, 'index']);
    Route::get('/modules/search', [ModuleController::class, 'search']);
    Route::get('/modules/{id}', [ModuleController::class, 'show']);
    Route::get('/modules/{id}/analytics', [ModuleController::class, 'analytics']);

    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::post('/assignments', [AssignmentController::class, 'store']);
    Route::put('/assignments/{id}', [AssignmentController::class, 'update']);
    Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy']);
    Route::post('/assignments/reorder', [AssignmentController::class, 'reorder']);
    Route::post('/assignments/bulk', [AssignmentController::class, 'bulk']);
    Route::post('/assignments/bulk/{operationId}/cancel', [AssignmentController::class, 'cancelBulkOperation']);
    Route::get('/assignments/conflicts', [AssignmentController::class, 'conflicts']);
    Route::post('/assignments/conflicts/{conflictId}/resolve', [AssignmentController::class, 'resolveConflict']);

    Route::get('/assignment-targets', [AssignmentTargetController::class, 'index']);
    Route::get('/course-templates', [CourseTemplateController::class, 'index']);
    Route::post('/course-templates', [CourseTemplateController::class, 'store']);
    Route::get('/enrollments', [EnrollmentController::class, 'index']);

    Route::get('/progress', [ProgressController::class, 'index']);
    Route::post('/progress', [ProgressController::class, 'store']);
    Route::post('/progress/batch', [ProgressController::class, 'batch']);
    Route::get('/progress/metrics', [ProgressController::class, 'metrics']);
    Route::get('/progress/export', [ProgressController::class, 'export']);

    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::post('/achievements', [AchievementController::class, 'store']);
    Route::get('/achievements/{id}', [AchievementController::class, 'show']);

    // User-specific endpoints with user ID parameter
    Route::get('/users/{userId}/progress', [ProgressController::class, 'index']);
    Route::get('/users/{userId}/analytics', function (Request $request, $userId) {
        // Basic analytics for specific user - expand based on your needs
        $analytics = [
            'total_courses' => CourseEnrollment::where('user_id', $userId)->count(),
            'total_tracks' => TrackEnrollment::where('user_id', $userId)->count(),
            'completed_lessons' => LessonProgress::where('user_id', $userId)->where('completed', true)->count(),
            'total_time_spent' => LessonProgress::where('user_id', $userId)->sum('time_spent') ?? 0,
        ];

        return response()->json($analytics);
    });
    Route::get('/users/{userId}/achievements', [AchievementController::class, 'index']);
    Route::get('/users/{userId}/milestones/check', function (Request $request, $userId) {
        // Placeholder for milestone checking - implement based on your business logic
        $milestones = [];
        return response()->json($milestones);
    });

    Route::get('/analytics', [AnalyticsController::class, 'index']);
});

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

    // Public Course Routes
    Route::prefix('courses')->name('api.courses.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CourseController::class, 'index'])->name('index');
        Route::get('/{slug}', [\App\Http\Controllers\Api\CourseController::class, 'show'])->name('show');
    });

    /*
    |--------------------------------------------------------------------------
    | Protected API Routes (v1) - Requires Authentication
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:web')->group(function () {

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index']);

        // Modules
        Route::get('/modules', [ModuleController::class, 'index']);
        Route::get('/modules/search', [ModuleController::class, 'search']);
        Route::get('/modules/{id}', [ModuleController::class, 'show']);
        Route::get('/modules/{id}/analytics', [ModuleController::class, 'analytics']);

        // Assignments
        Route::get('/assignments', [AssignmentController::class, 'index']);
        Route::post('/assignments', [AssignmentController::class, 'store']);
        Route::put('/assignments/{id}', [AssignmentController::class, 'update']);
        Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy']);
        Route::post('/assignments/reorder', [AssignmentController::class, 'reorder']);
        Route::post('/assignments/bulk', [AssignmentController::class, 'bulk']);
        Route::post('/assignments/bulk/{operationId}/cancel', [AssignmentController::class, 'cancelBulkOperation']);
        Route::get('/assignments/conflicts', [AssignmentController::class, 'conflicts']);
        Route::post('/assignments/conflicts/{conflictId}/resolve', [AssignmentController::class, 'resolveConflict']);

        // Assignment Targets
        Route::get('/assignment-targets', [AssignmentTargetController::class, 'index']);

        // Course Templates
        Route::get('/course-templates', [CourseTemplateController::class, 'index']);
        Route::post('/course-templates', [CourseTemplateController::class, 'store']);

        // Enrollments
        Route::get('/enrollments', [EnrollmentController::class, 'index']);

        // Progress
        Route::get('/progress', [ProgressController::class, 'index']);
        Route::post('/progress', [ProgressController::class, 'store']);
        Route::post('/progress/batch', [ProgressController::class, 'batch']);
        Route::get('/progress/metrics', [ProgressController::class, 'metrics']);
        Route::get('/progress/export', [ProgressController::class, 'export']);

        // Achievements
        Route::get('/achievements', [AchievementController::class, 'index']);
        Route::post('/achievements', [AchievementController::class, 'store']);
        Route::get('/achievements/{id}', [AchievementController::class, 'show']);

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

        // Course Management Routes
        Route::prefix('courses')->group(function () {
            // Public course routes (moved to public section above)

            // Course Management (Admin)
            Route::post('/', [\App\Http\Controllers\Api\CourseController::class, 'store']);
            Route::put('/{slug}', [\App\Http\Controllers\Api\CourseController::class, 'update']);
            Route::delete('/{slug}', [\App\Http\Controllers\Api\CourseController::class, 'destroy']);

            // Course Module Management
            Route::get('/{slug}/modules', [\App\Http\Controllers\Api\CourseController::class, 'modules']);
            Route::post('/{slug}/modules', [\App\Http\Controllers\Api\CourseController::class, 'assignModule']);
            Route::delete('/{slug}/modules', [\App\Http\Controllers\Api\CourseController::class, 'removeModule']);
            Route::put('/{slug}/modules/order', [\App\Http\Controllers\Api\CourseController::class, 'updateModuleOrder']);

            // Course Enrollment Management
            Route::post('/{courseSlug}/enroll', [\App\Http\Controllers\Api\CourseEnrollmentController::class, 'enroll']);
            Route::delete('/{courseSlug}/enroll', [\App\Http\Controllers\Api\CourseEnrollmentController::class, 'unenroll']);
            Route::get('/{courseSlug}/enrollment/status', [\App\Http\Controllers\Api\CourseEnrollmentController::class, 'status']);
            Route::put('/{courseSlug}/enrollment/progress', [\App\Http\Controllers\Api\CourseEnrollmentController::class, 'updateProgress']);
        });

        // User Course Enrollments
        Route::get('/user/course-enrollments', [\App\Http\Controllers\Api\CourseEnrollmentController::class, 'userEnrollments']);

        // Administrative Course Management
        Route::prefix('admin')->group(function () {
            Route::post('/course-enrollments/bulk', [\App\Http\Controllers\Api\CourseEnrollmentController::class, 'bulkEnroll']);
            Route::get('/course-enrollments/statistics', [\App\Http\Controllers\Api\CourseEnrollmentController::class, 'statistics']);
        });
    });
});
