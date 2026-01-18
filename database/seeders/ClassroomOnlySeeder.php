<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ClassroomOnlySeeder extends Seeder
{
    /**
     * Seed only classroom-related data.
     * This seeder is useful when you want to add classroom data
     * without affecting existing playlists and posts.
     */
    public function run(): void
    {
        $this->command->info('Seeding classroom data only...');

        // Ensure we have an admin user
        if (!User::where('role', 'admin')->exists()) {
            User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@coderium.id',
                'password' => 'password',
                'role' => 'admin',
            ]);
        }

        $this->call([
            AchievementSeeder::class,
            CertificateTemplateSeeder::class,
            ClassroomSeeder::class,
        ]);

        $this->command->info('Classroom data seeded successfully!');
    }
}
