<?php

namespace Database\Seeders;

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlaylistSeeder extends Seeder
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

        $playlists = [
            [
                'title' => 'Web Development Basics',
                'description' => 'Learn the fundamentals of web development including HTML, CSS, and JavaScript',
                'slug' => 'web-development-basics',
                'cover' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085',
                'is_published' => true,
                'order' => 1,
            ],
            [
                'title' => 'Laravel Mastery',
                'description' => 'Master Laravel framework from beginner to advanced level',
                'slug' => 'laravel-mastery',
                'cover' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c',
                'is_published' => true,
                'order' => 2,
            ],
            [
                'title' => 'Vue.js Essentials',
                'description' => 'Everything you need to know about Vue.js framework',
                'slug' => 'vuejs-essentials',
                'cover' => 'https://images.unsplash.com/photo-1633356122544-f134324a6cee',
                'is_published' => true,
                'order' => 3,
            ],
            [
                'title' => 'Mobile App Development',
                'description' => 'Build modern mobile applications for iOS and Android',
                'slug' => 'mobile-app-development',
                'cover' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c',
                'is_published' => true,
                'order' => 4,
            ],
            [
                'title' => 'UI/UX Design Principles',
                'description' => 'Learn the principles of great user interface and user experience design',
                'slug' => 'uiux-design-principles',
                'cover' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5',
                'is_published' => true,
                'order' => 5,
            ],
        ];

        foreach ($playlists as $playlist) {
            Playlist::create([
                ...$playlist,
                'user_id' => $user->id,
            ]);
        }

        $this->command->info('Playlists seeded successfully!');
    }
}
