<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@coderium.id',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->call([
            PlaylistSeeder::class,
            PostSeeder::class,
            MediaSeeder::class,
            PlaylistPostSeeder::class,
            AchievementSeeder::class,
            CertificateTemplateSeeder::class,
            ClassroomSeeder::class,
            CourseSeeder::class,
        ]);
    }
}
