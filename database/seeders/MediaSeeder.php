<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class MediaSeeder extends Seeder
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

        // Sample media files for carousel posts
        $carouselMedia = [
            [
                'name' => 'Web Design Trends Hero',
                'file_name' => 'web-design-trends-01.jpg',
                'mime_type' => 'image/jpeg',
                'path' => 'media/web-design-trends-01.jpg',
                'collection_name' => 'carousel',
                'size' => 245680,
                'url' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5',
            ],
            [
                'name' => 'Web Design Trends Modern',
                'file_name' => 'web-design-trends-02.jpg',
                'mime_type' => 'image/jpeg',
                'path' => 'media/web-design-trends-02.jpg',
                'collection_name' => 'carousel',
                'size' => 198450,
                'url' => 'https://images.unsplash.com/photo-1558655146-9f40138edfeb',
            ],
            [
                'name' => 'Web Design Trends Minimalist',
                'file_name' => 'web-design-trends-03.jpg',
                'mime_type' => 'image/jpeg',
                'path' => 'media/web-design-trends-03.jpg',
                'collection_name' => 'carousel',
                'size' => 210340,
                'url' => 'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c',
            ],
            [
                'name' => 'Web Design Trends Bold',
                'file_name' => 'web-design-trends-04.jpg',
                'mime_type' => 'image/jpeg',
                'path' => 'media/web-design-trends-04.jpg',
                'collection_name' => 'carousel',
                'size' => 235120,
                'url' => 'https://images.unsplash.com/photo-1626785774573-4b799315345d',
            ],
        ];

        $cssGridMedia = [
            [
                'name' => 'CSS Grid Layout Example 1',
                'file_name' => 'css-grid-01.jpg',
                'mime_type' => 'image/jpeg',
                'path' => 'media/css-grid-01.jpg',
                'collection_name' => 'carousel',
                'size' => 189750,
                'url' => 'https://images.unsplash.com/photo-1507721999472-8ed4421c4af2',
            ],
            [
                'name' => 'CSS Grid Layout Example 2',
                'file_name' => 'css-grid-02.jpg',
                'mime_type' => 'image/jpeg',
                'path' => 'media/css-grid-02.jpg',
                'collection_name' => 'carousel',
                'size' => 202890,
                'url' => 'https://images.unsplash.com/photo-1517134191118-9d595e4c8c2b',
            ],
            [
                'name' => 'CSS Grid Layout Example 3',
                'file_name' => 'css-grid-03.jpg',
                'mime_type' => 'image/jpeg',
                'path' => 'media/css-grid-03.jpg',
                'collection_name' => 'carousel',
                'size' => 195440,
                'url' => 'https://images.unsplash.com/photo-1545665225-b23b99e4d45e',
            ],
        ];

        // Sample video media
        $videoMedia = [
            [
                'name' => 'Laravel REST API Tutorial',
                'file_name' => 'laravel-api-tutorial.mp4',
                'mime_type' => 'video/mp4',
                'path' => 'media/laravel-api-tutorial.mp4',
                'collection_name' => 'video',
                'size' => 52428800, // 50MB
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'custom_properties' => [
                    'duration' => '25:30',
                    'provider' => 'youtube',
                    'thumbnail' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085',
                ],
            ],
            [
                'name' => 'Tailwind CSS Responsive Design',
                'file_name' => 'tailwind-responsive-design.mp4',
                'mime_type' => 'video/mp4',
                'path' => 'media/tailwind-responsive-design.mp4',
                'collection_name' => 'video',
                'size' => 38654705, // 37MB
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'custom_properties' => [
                    'duration' => '18:45',
                    'provider' => 'youtube',
                    'thumbnail' => 'https://images.unsplash.com/photo-1547658719-da2b51169166',
                ],
            ],
        ];

        // Create media records and attach to posts
        $posts = Post::all();

        // Attach carousel media to "Modern Web Design Trends 2025" post
        $carouselPost1 = $posts->firstWhere('slug', 'modern-web-design-trends-2025');
        if ($carouselPost1) {
            foreach ($carouselMedia as $index => $mediaData) {
                $media = Media::create([
                    'name' => $mediaData['name'],
                    'file_name' => $mediaData['file_name'],
                    'mime_type' => $mediaData['mime_type'],
                    'path' => $mediaData['path'],
                    'collection_name' => $mediaData['collection_name'],
                    'size' => $mediaData['size'],
                    'custom_properties' => ['url' => $mediaData['url']],
                    'user_id' => $user->id,
                ]);

                $carouselPost1->media()->attach($media->id, [
                    'tag' => 'carousel',
                    'order' => $index + 1,
                ]);
            }
        }

        // Attach CSS Grid media to "CSS Grid Layout Examples" post
        $carouselPost2 = $posts->firstWhere('slug', 'css-grid-layout-examples');
        if ($carouselPost2) {
            foreach ($cssGridMedia as $index => $mediaData) {
                $media = Media::create([
                    'name' => $mediaData['name'],
                    'file_name' => $mediaData['file_name'],
                    'mime_type' => $mediaData['mime_type'],
                    'path' => $mediaData['path'],
                    'collection_name' => $mediaData['collection_name'],
                    'size' => $mediaData['size'],
                    'custom_properties' => ['url' => $mediaData['url']],
                    'user_id' => $user->id,
                ]);

                $carouselPost2->media()->attach($media->id, [
                    'tag' => 'carousel',
                    'order' => $index + 1,
                ]);
            }
        }

        // Attach video media to Laravel tutorial post
        $videoPost1 = $posts->firstWhere('slug', 'laravel-tutorial-building-rest-api');
        if ($videoPost1) {
            $media = Media::create([
                'name' => $videoMedia[0]['name'],
                'file_name' => $videoMedia[0]['file_name'],
                'mime_type' => $videoMedia[0]['mime_type'],
                'path' => $videoMedia[0]['path'],
                'collection_name' => $videoMedia[0]['collection_name'],
                'size' => $videoMedia[0]['size'],
                'custom_properties' => array_merge(
                    ['url' => $videoMedia[0]['url']],
                    $videoMedia[0]['custom_properties']
                ),
                'user_id' => $user->id,
            ]);

            $videoPost1->media()->attach($media->id, [
                'tag' => 'video',
                'order' => 1,
            ]);
        }

        // Attach video media to Tailwind CSS post
        $videoPost2 = $posts->firstWhere('slug', 'responsive-design-with-tailwind-css');
        if ($videoPost2) {
            $media = Media::create([
                'name' => $videoMedia[1]['name'],
                'file_name' => $videoMedia[1]['file_name'],
                'mime_type' => $videoMedia[1]['mime_type'],
                'path' => $videoMedia[1]['path'],
                'collection_name' => $videoMedia[1]['collection_name'],
                'size' => $videoMedia[1]['size'],
                'custom_properties' => array_merge(
                    ['url' => $videoMedia[1]['url']],
                    $videoMedia[1]['custom_properties']
                ),
                'user_id' => $user->id,
            ]);

            $videoPost2->media()->attach($media->id, [
                'tag' => 'video',
                'order' => 1,
            ]);
        }

        $this->command->info('Media seeded successfully!');
        $this->command->info('Created ' . Media::count() . ' media records.');
    }
}
