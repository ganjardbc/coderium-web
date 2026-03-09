<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Search
// Route::get('/search', [App\Http\Controllers\SearchController::class, 'index'])->name('search');

// Post List
Route::get('/explore', [App\Http\Controllers\SearchController::class, 'index'])->name('postlists.index');

// Public posts
Route::get('/posts', [App\Http\Controllers\PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{slug}', [App\Http\Controllers\PostController::class, 'show'])->name('posts.show');

// Public playlists
Route::get('/playlists', [App\Http\Controllers\PlaylistController::class, 'index'])->name('playlists.index');
Route::get('/playlists/{slug}', [App\Http\Controllers\PlaylistController::class, 'show'])->name('playlists.show');

// Public courses
Route::get('/courses', [App\Http\Controllers\CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course:slug}', [App\Http\Controllers\CourseController::class, 'show'])->name('courses.show');

// Course module routes (require authentication)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/courses/{course:slug}/modules/{module}', [App\Http\Controllers\CourseController::class, 'courseModuleView'])->name('courses.modules.show');
    Route::get('/courses/{course:slug}/modules/{module}/lessons/{lesson}', [App\Http\Controllers\CourseController::class, 'courseLessonView'])->name('courses.lessons.show');
    Route::get('/courses/{course:slug}/modules/{module}/assessments/{assessment}', [App\Http\Controllers\CourseController::class, 'courseAssessmentView'])->name('courses.assessments.show');
});

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Playlists
    Route::resource('playlists', App\Http\Controllers\Admin\PlaylistController::class)->except(['show']);

    // Posts
    Route::resource('posts', App\Http\Controllers\Admin\PostController::class);

    // Module Management (Standalone)
    Route::prefix('modules')->name('modules.')->middleware(['role:admin_only'])->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ModuleController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\ModuleController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\ModuleController::class, 'store'])->name('store');
        Route::get('/{module}', [App\Http\Controllers\Admin\ModuleController::class, 'show'])->name('show');
        Route::get('/{module}/edit', [App\Http\Controllers\Admin\ModuleController::class, 'edit'])->name('edit');
        Route::put('/{module}', [App\Http\Controllers\Admin\ModuleController::class, 'update'])->name('update');
        Route::delete('/{module}', [App\Http\Controllers\Admin\ModuleController::class, 'destroy'])->name('destroy');
    });

    // Lesson Management (Standalone)
    Route::prefix('lessons')->name('lessons.')->middleware(['role:admin_only'])->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\LessonController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\LessonController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\LessonController::class, 'store'])->name('store');
        Route::get('/{lesson}', [App\Http\Controllers\Admin\LessonController::class, 'show'])->name('show');
        Route::get('/{lesson}/edit', [App\Http\Controllers\Admin\LessonController::class, 'edit'])->name('edit');
        Route::put('/{lesson}', [App\Http\Controllers\Admin\LessonController::class, 'update'])->name('update');
        Route::delete('/{lesson}', [App\Http\Controllers\Admin\LessonController::class, 'destroy'])->name('destroy');
    });

    // Assessment Management (Standalone)
    Route::prefix('assessments')->name('assessments.')->middleware(['role:admin_only'])->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\AssessmentController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\AssessmentController::class, 'form'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\AssessmentController::class, 'store'])->name('store');
        Route::get('/{assessment}', [App\Http\Controllers\Admin\AssessmentController::class, 'show'])->name('show');
        Route::get('/{assessment}/edit', [App\Http\Controllers\Admin\AssessmentController::class, 'form'])->name('edit');
        Route::put('/{assessment}', [App\Http\Controllers\Admin\AssessmentController::class, 'update'])->name('update');
        Route::delete('/{assessment}', [App\Http\Controllers\Admin\AssessmentController::class, 'destroy'])->name('destroy');
    });

    // Course Management (Standalone)
    Route::prefix('courses')->name('courses.')->middleware(['role:admin_only'])->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CourseAdminController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\CourseAdminController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\CourseAdminController::class, 'store'])->name('store');
        Route::get('/{course}', [App\Http\Controllers\Admin\CourseAdminController::class, 'show'])->name('show');
        Route::get('/{course}/edit', [App\Http\Controllers\Admin\CourseAdminController::class, 'edit'])->name('edit');
        Route::put('/{course}', [App\Http\Controllers\Admin\CourseAdminController::class, 'update'])->name('update');
        Route::delete('/{course}', [App\Http\Controllers\Admin\CourseAdminController::class, 'destroy'])->name('destroy');

        // Module management for courses
        Route::get('/{course}/modules', [App\Http\Controllers\Admin\CourseAdminController::class, 'modules'])->name('modules');
        Route::post('/{course}/modules', [App\Http\Controllers\Admin\CourseAdminController::class, 'assignModule'])->name('modules.assign');
        Route::put('/{course}/modules/bulk', [App\Http\Controllers\Admin\CourseAdminController::class, 'bulkUpdateModules'])->name('modules.bulk');
        Route::delete('/{course}/modules/{module}', [App\Http\Controllers\Admin\CourseAdminController::class, 'removeModule'])->name('modules.remove');
        Route::put('/{course}/modules/{module}/order', [App\Http\Controllers\Admin\CourseAdminController::class, 'updateModuleOrder'])->name('modules.order');
        Route::put('/{course}/modules/{module}/required', [App\Http\Controllers\Admin\CourseAdminController::class, 'updateModuleRequired'])->name('modules.required');
    });

    // Tracks
    Route::prefix('tracks')->name('tracks.')->middleware(['role:admin_only'])->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\TrackController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\TrackController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\TrackController::class, 'store'])->name('store');
        Route::get('/{track}', [App\Http\Controllers\Admin\TrackController::class, 'show'])->name('show');
        Route::get('/{track}/edit', [App\Http\Controllers\Admin\TrackController::class, 'edit'])->name('edit');
        Route::put('/{track}', [App\Http\Controllers\Admin\TrackController::class, 'update'])->name('update');
        Route::delete('/{track}', [App\Http\Controllers\Admin\TrackController::class, 'destroy'])->name('destroy');

        // Track levels
        Route::get('/{track}/levels', [App\Http\Controllers\Admin\TrackController::class, 'levels'])->name('levels');
    });

    // Levels
    Route::prefix('levels')->name('levels.')->middleware(['role:admin_only'])->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\LevelController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\LevelController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\LevelController::class, 'store'])->name('store');
        Route::get('/{level}', [App\Http\Controllers\Admin\LevelController::class, 'show'])->name('show');
        Route::get('/{level}/edit', [App\Http\Controllers\Admin\LevelController::class, 'edit'])->name('edit');
        Route::put('/{level}', [App\Http\Controllers\Admin\LevelController::class, 'update'])->name('update');
        Route::delete('/{level}', [App\Http\Controllers\Admin\LevelController::class, 'destroy'])->name('destroy');
        Route::put('/{level}/move', [App\Http\Controllers\Admin\LevelController::class, 'move'])->name('move');
    });

    // // Classroom Management
    // Route::prefix('classroom')->name('classroom.')->middleware(['role:admin_only'])->group(function () {
    //     // Classroom Dashboard - redirects to courses
    //     Route::get('/', [App\Http\Controllers\Admin\ClassroomController::class, 'index'])->name('index');

    //     // Tracks
    //     Route::resource('tracks', App\Http\Controllers\Admin\TrackController::class)->except(['show']);

    //     // Levels
    //     Route::resource('levels', App\Http\Controllers\Admin\LevelController::class)->except(['show']);

    //     // Modules
    //     Route::resource('modules', App\Http\Controllers\Admin\ModuleController::class)->except(['show']);

    //     // Lessons
    //     Route::resource('lessons', App\Http\Controllers\Admin\LessonController::class)->except(['show']);

    //     // Assessments
    //     Route::resource('assessments', App\Http\Controllers\Admin\AssessmentController::class)->except(['show']);

    //     // Certificate Templates
    //     Route::post('/certificate-templates', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'store'])->name('certificate-templates.store');
    //     Route::put('/certificate-templates/{template}', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'update'])->name('certificate-templates.update');
    //     Route::delete('/certificate-templates/{template}', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'destroy'])->name('certificate-templates.destroy');
    //     Route::post('/certificate-templates/{template}/set-default', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'setDefault'])->name('certificate-templates.set-default');

    //     // Certificate Management
    //     Route::get('/certificates/{certificate}/download', [App\Http\Controllers\Admin\ClassroomController::class, 'downloadCertificate'])->name('certificates.download');
    //     Route::post('/certificates/{certificate}/resend', [App\Http\Controllers\Admin\ClassroomController::class, 'resendCertificate'])->name('certificates.resend');
    //     Route::delete('/certificates/{certificate}', [App\Http\Controllers\Admin\ClassroomController::class, 'revokeCertificate'])->name('certificates.revoke');
    //     Route::post('/certificates/bulk-generate', [App\Http\Controllers\Admin\ClassroomController::class, 'bulkGenerateCertificates'])->name('certificates.bulk-generate');
    // });
});

/*
|--------------------------------------------------------------------------
| Classroom Routes
|--------------------------------------------------------------------------
*/
Route::prefix('classroom')->name('classroom.')->group(function () {
    // Public classroom routes
    Route::get('/', [App\Http\Controllers\ClassroomController::class, 'tracks'])->name('tracks.index');
    Route::get('/tracks', [App\Http\Controllers\ClassroomController::class, 'tracks'])->name('tracks.list');
    Route::get('/tracks/{slug}', [App\Http\Controllers\ClassroomController::class, 'trackDetail'])->name('tracks.show');

    // Authenticated classroom routes
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/levels/{level}', [App\Http\Controllers\ClassroomController::class, 'levelView'])->name('levels.show');
        Route::get('/modules/{module}', [App\Http\Controllers\ClassroomController::class, 'moduleView'])->name('modules.show');
        Route::get('/lessons/{lesson}', [App\Http\Controllers\ClassroomController::class, 'lessonView'])->name('lessons.show');
        Route::get('/assessments/{assessment}', [App\Http\Controllers\ClassroomController::class, 'assessmentView'])->name('assessments.show');
        Route::get('/assessments/{assessment}/results', [App\Http\Controllers\ClassroomController::class, 'assessmentResults'])->name('assessments.results');
    });
});

/*
|--------------------------------------------------------------------------
| Course-specific Routes (outside classroom prefix)
|--------------------------------------------------------------------------
*/
// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::get('/courses/{course:slug}/modules/{module}', [App\Http\Controllers\ClassroomController::class, 'courseModuleView'])->name('courses.modules.show');
//     Route::get('/courses/{course:slug}/modules/{module}/lessons/{lesson}', [App\Http\Controllers\ClassroomController::class, 'courseLessonView'])->name('courses.lessons.show');
//     Route::get('/courses/{course:slug}/modules/{module}/assessments/{assessment}', [App\Http\Controllers\ClassroomController::class, 'courseAssessmentView'])->name('courses.assessments.show');
// });

require __DIR__.'/settings.php';
