<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Search
// Route::get('/search', [App\Http\Controllers\SearchController::class, 'index'])->name('search');

// Post List
Route::get('/explore', [App\Http\Controllers\SearchController::class, 'index'])->name('postlists.index');

// Public playlists
Route::get('/playlists', [App\Http\Controllers\PlaylistController::class, 'index'])->name('playlists.index');
Route::get('/playlists/{slug}', [App\Http\Controllers\PlaylistController::class, 'show'])->name('playlists.show');

// Public post detail
Route::get('/posts/{slug}', [App\Http\Controllers\PostController::class, 'show'])->name('posts.show');

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
    Route::resource('posts', App\Http\Controllers\Admin\PostController::class)->except(['show']);

    // Classroom Management
    Route::prefix('classroom')->name('classroom.')->middleware(['role:admin_only'])->group(function () {
        // Classroom Dashboard
        Route::get('/', [App\Http\Controllers\Admin\ClassroomController::class, 'index'])->name('index');
        Route::get('/progress', [App\Http\Controllers\Admin\ClassroomController::class, 'progressDashboard'])->name('progress');
        Route::get('/certificates', [App\Http\Controllers\Admin\ClassroomController::class, 'certificateManager'])->name('certificates');

        // Tracks
        Route::resource('tracks', App\Http\Controllers\Admin\TrackController::class)->except(['show']);

        // Levels
        Route::resource('levels', App\Http\Controllers\Admin\LevelController::class)->except(['show']);

        // Modules
        Route::resource('modules', App\Http\Controllers\Admin\ModuleController::class)->except(['show']);

        // Lessons
        Route::resource('lessons', App\Http\Controllers\Admin\LessonController::class)->except(['show']);

        // Assessments
        Route::resource('assessments', App\Http\Controllers\Admin\AssessmentController::class)->except(['show']);

        // Certificate Templates
        Route::post('/certificate-templates', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'store'])->name('certificate-templates.store');
        Route::put('/certificate-templates/{template}', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'update'])->name('certificate-templates.update');
        Route::delete('/certificate-templates/{template}', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'destroy'])->name('certificate-templates.destroy');
        Route::post('/certificate-templates/{template}/set-default', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'setDefault'])->name('certificate-templates.set-default');

        // Certificate Management
        Route::get('/certificates/{certificate}/download', [App\Http\Controllers\Admin\ClassroomController::class, 'downloadCertificate'])->name('certificates.download');
        Route::post('/certificates/{certificate}/resend', [App\Http\Controllers\Admin\ClassroomController::class, 'resendCertificate'])->name('certificates.resend');
        Route::delete('/certificates/{certificate}', [App\Http\Controllers\Admin\ClassroomController::class, 'revokeCertificate'])->name('certificates.revoke');
        Route::post('/certificates/bulk-generate', [App\Http\Controllers\Admin\ClassroomController::class, 'bulkGenerateCertificates'])->name('certificates.bulk-generate');
    });
});

/*
|--------------------------------------------------------------------------
| Classroom Routes
|--------------------------------------------------------------------------
*/
Route::prefix('classroom')->name('classroom.')->group(function () {
    // Public classroom routes
    Route::get('/tracks', [App\Http\Controllers\ClassroomController::class, 'tracks'])->name('tracks.index');
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

require __DIR__.'/settings.php';
