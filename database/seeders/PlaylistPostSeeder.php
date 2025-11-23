<?php

namespace Database\Seeders;

use App\Models\Playlist;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlaylistPostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (!$user) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        // Get playlists and posts
        $webDevPlaylist = Playlist::where('slug', 'web-development-basics')->first();
        $laravelPlaylist = Playlist::where('slug', 'laravel-mastery')->first();
        $vuePlaylist = Playlist::where('slug', 'vuejs-essentials')->first();
        $uiuxPlaylist = Playlist::where('slug', 'uiux-design-principles')->first();

        // Attach posts to Web Development Basics playlist
        if ($webDevPlaylist) {
            $posts = Post::whereIn('slug', [
                'javascript-es2024-features',
                'css-grid-layout-examples',
                'responsive-design-with-tailwind-css',
                'database-design-best-practices',
            ])->get();

            $order = 1;
            foreach ($posts as $post) {
                $webDevPlaylist->posts()->attach($post->id, [
                    'user_id' => $user->id,
                    'order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Attach posts to Laravel Mastery playlist
        if ($laravelPlaylist) {
            $posts = Post::whereIn('slug', [
                'getting-started-with-laravel-11',
                'laravel-tutorial-building-rest-api',
                'database-design-best-practices',
            ])->get();

            $order = 1;
            foreach ($posts as $post) {
                $laravelPlaylist->posts()->attach($post->id, [
                    'user_id' => $user->id,
                    'order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Attach posts to Vue.js Essentials playlist
        if ($vuePlaylist) {
            $posts = Post::whereIn('slug', [
                'vue-3-composition-api-explained',
                'javascript-es2024-features',
                'responsive-design-with-tailwind-css',
            ])->get();

            $order = 1;
            foreach ($posts as $post) {
                $vuePlaylist->posts()->attach($post->id, [
                    'user_id' => $user->id,
                    'order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Attach posts to UI/UX Design Principles playlist
        if ($uiuxPlaylist) {
            $posts = Post::whereIn('slug', [
                'modern-web-design-trends-2025',
                'css-grid-layout-examples',
                'responsive-design-with-tailwind-css',
            ])->get();

            $order = 1;
            foreach ($posts as $post) {
                $uiuxPlaylist->posts()->attach($post->id, [
                    'user_id' => $user->id,
                    'order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Playlist-Post relationships seeded successfully!');
    }
}
