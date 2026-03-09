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
});

require __DIR__.'/settings.php';
