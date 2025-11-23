<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
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

        $posts = [
            // Articles
            [
                'title' => 'Getting Started with Laravel 11',
                'subtitle' => 'A comprehensive guide to building modern web applications',
                'slug' => 'getting-started-with-laravel-11',
                'content' => '<h2>Introduction</h2><p>Laravel 11 brings exciting new features and improvements to the framework. In this article, we\'ll explore the key changes and how to get started.</p><h3>Key Features</h3><ul><li>Improved performance</li><li>New directory structure</li><li>Enhanced testing tools</li></ul>',
                'type' => 'article',
                'tags' => json_encode(['laravel', 'php', 'web-development']),
                'cover' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c',
                'meta_description' => 'Learn how to get started with Laravel 11 and build modern web applications',
                'meta_keywords' => 'laravel, laravel 11, php, web development',
                'is_published' => true,
                'published_at' => now()->subDays(5),
                'views_count' => 1250,
                'likes_count' => 89,
            ],
            [
                'title' => 'Vue 3 Composition API Explained',
                'subtitle' => 'Understanding the new way to write Vue components',
                'slug' => 'vue-3-composition-api-explained',
                'content' => '<h2>What is Composition API?</h2><p>The Composition API is a new way to organize and reuse logic in Vue components. It provides better TypeScript support and more flexible code organization.</p>',
                'type' => 'article',
                'tags' => json_encode(['vue', 'javascript', 'frontend']),
                'cover' => 'https://images.unsplash.com/photo-1633356122544-f134324a6cee',
                'meta_description' => 'Deep dive into Vue 3 Composition API and learn how to write better Vue components',
                'meta_keywords' => 'vue 3, composition api, javascript, frontend',
                'is_published' => true,
                'published_at' => now()->subDays(3),
                'views_count' => 980,
                'likes_count' => 67,
            ],
            // Carousel
            [
                'title' => 'Modern Web Design Trends 2025',
                'subtitle' => 'Explore the latest trends in web design',
                'slug' => 'modern-web-design-trends-2025',
                'content' => '<p>A visual journey through the most popular web design trends of 2025.</p>',
                'type' => 'carousel',
                'tags' => json_encode(['design', 'trends', 'ui', 'ux']),
                'cover' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5',
                'media' => json_encode([
                    'https://images.unsplash.com/photo-1561070791-2526d30994b5',
                    'https://images.unsplash.com/photo-1558655146-9f40138edfeb',
                    'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c',
                    'https://images.unsplash.com/photo-1626785774573-4b799315345d',
                ]),
                'meta_description' => 'Discover the latest web design trends that will shape 2025',
                'meta_keywords' => 'web design, trends, ui design, ux design',
                'is_published' => true,
                'published_at' => now()->subDays(7),
                'views_count' => 2340,
                'likes_count' => 156,
            ],
            [
                'title' => 'CSS Grid Layout Examples',
                'subtitle' => 'Beautiful layouts using CSS Grid',
                'slug' => 'css-grid-layout-examples',
                'content' => '<p>Learn CSS Grid through practical examples and visual demonstrations.</p>',
                'type' => 'carousel',
                'tags' => json_encode(['css', 'grid', 'layout', 'frontend']),
                'cover' => 'https://images.unsplash.com/photo-1507721999472-8ed4421c4af2',
                'media' => json_encode([
                    'https://images.unsplash.com/photo-1507721999472-8ed4421c4af2',
                    'https://images.unsplash.com/photo-1517134191118-9d595e4c8c2b',
                    'https://images.unsplash.com/photo-1545665225-b23b99e4d45e',
                ]),
                'meta_description' => 'Master CSS Grid with these practical examples and layouts',
                'meta_keywords' => 'css grid, layout, css, responsive design',
                'is_published' => true,
                'published_at' => now()->subDays(10),
                'views_count' => 1670,
                'likes_count' => 103,
            ],
            // Videos
            [
                'title' => 'Laravel Tutorial: Building a REST API',
                'subtitle' => 'Step-by-step guide to creating RESTful APIs',
                'slug' => 'laravel-tutorial-building-rest-api',
                'content' => '<p>In this video tutorial, you\'ll learn how to build a complete REST API using Laravel.</p>',
                'type' => 'video',
                'tags' => json_encode(['laravel', 'api', 'tutorial', 'backend']),
                'cover' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085',
                'media' => json_encode([
                    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'duration' => '25:30',
                    'provider' => 'youtube'
                ]),
                'meta_description' => 'Learn how to build RESTful APIs with Laravel in this comprehensive video tutorial',
                'meta_keywords' => 'laravel api, rest api, laravel tutorial, backend development',
                'is_published' => true,
                'published_at' => now()->subDays(2),
                'views_count' => 3420,
                'likes_count' => 234,
            ],
            [
                'title' => 'Responsive Design with Tailwind CSS',
                'subtitle' => 'Build responsive websites faster',
                'slug' => 'responsive-design-with-tailwind-css',
                'content' => '<p>Learn how to create beautiful, responsive designs using Tailwind CSS utility classes.</p>',
                'type' => 'video',
                'tags' => json_encode(['tailwind', 'css', 'responsive', 'frontend']),
                'cover' => 'https://images.unsplash.com/photo-1547658719-da2b51169166',
                'media' => json_encode([
                    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'duration' => '18:45',
                    'provider' => 'youtube'
                ]),
                'meta_description' => 'Master responsive web design with Tailwind CSS in this practical video tutorial',
                'meta_keywords' => 'tailwind css, responsive design, css framework, frontend',
                'is_published' => true,
                'published_at' => now()->subDays(1),
                'views_count' => 2890,
                'likes_count' => 187,
            ],
            [
                'title' => 'Database Design Best Practices',
                'subtitle' => 'Design efficient and scalable databases',
                'slug' => 'database-design-best-practices',
                'content' => '<h2>Introduction to Database Design</h2><p>Proper database design is crucial for building scalable applications. In this guide, we\'ll explore the key principles and best practices.</p>',
                'type' => 'article',
                'tags' => json_encode(['database', 'mysql', 'design', 'backend']),
                'cover' => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d',
                'meta_description' => 'Learn database design best practices for building scalable applications',
                'meta_keywords' => 'database design, mysql, postgresql, data modeling',
                'is_published' => true,
                'published_at' => now()->subDays(8),
                'views_count' => 1560,
                'likes_count' => 92,
            ],
            [
                'title' => 'JavaScript ES2024 Features',
                'subtitle' => 'What\'s new in JavaScript',
                'slug' => 'javascript-es2024-features',
                'content' => '<h2>New Features in ES2024</h2><p>JavaScript continues to evolve with new features that make development easier and more powerful.</p>',
                'type' => 'article',
                'tags' => json_encode(['javascript', 'es2024', 'frontend']),
                'cover' => 'https://images.unsplash.com/photo-1579468118864-1b9ea3c0db4a',
                'meta_description' => 'Explore the latest JavaScript ES2024 features and improvements',
                'meta_keywords' => 'javascript, es2024, ecmascript, frontend development',
                'is_published' => true,
                'published_at' => now()->subDays(4),
                'views_count' => 2100,
                'likes_count' => 145,
            ],
        ];

        foreach ($posts as $post) {
            Post::create([
                ...$post,
                'user_id' => $user->id,
            ]);
        }

        $this->command->info('Posts seeded successfully!');
    }
}
