<?php

namespace App\Services;

use App\Models\Track;
use App\Models\User;
use App\Models\TrackEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackService
{
    /**
     * Create a new track.
     *
     * @param array $data
     * @return Track
     * @throws ValidationException
     */
    public function createTrack(array $data): Track
    {
        $this->validateTrackData($data);

        // Generate slug if not provided
        if (!isset($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['title']);
        }

        // Validate hierarchy constraints
        $this->validateHierarchyConstraints($data);

        return DB::transaction(function () use ($data) {
            return Track::create($data);
        });
    }

    /**
     * Update an existing track.
     *
     * @param Track $track
     * @param array $data
     * @return Track
     * @throws ValidationException
     */
    public function updateTrack(Track $track, array $data): Track
    {
        $this->validateTrackData($data, $track->id);

        // Generate new slug if title changed
        if (isset($data['title']) && $data['title'] !== $track->title && !isset($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $track->id);
        }

        // Validate hierarchy constraints
        $this->validateHierarchyConstraints($data);

        return DB::transaction(function () use ($track, $data) {
            $track->update($data);
            return $track->fresh();
        });
    }

    /**
     * Publish a track.
     *
     * @param Track $track
     * @return bool
     * @throws ValidationException
     */
    public function publishTrack(Track $track): bool
    {
        // Validate track is ready for publishing
        $this->validateTrackForPublishing($track);

        return DB::transaction(function () use ($track) {
            return $track->update(['is_published' => true]);
        });
    }

    /**
     * Get all published tracks.
     *
     * @return Collection
     */
    public function getPublishedTracks(): Collection
    {
        return Track::where('is_published', true)
            ->with(['instructor', 'levels.modules.lessons'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get track with progress for a specific user.
     *
     * @param Track $track
     * @param User $user
     * @return array
     */
    public function getTrackWithProgress(Track $track, User $user): array
    {
        $enrollment = $track->enrollments()
            ->where('user_id', $user->id)
            ->first();

        if (!$enrollment) {
            return [
                'track' => $track->load(['levels.modules.lessons', 'instructor']),
                'enrollment' => null,
                'progress' => [
                    'overall_percentage' => 0,
                    'levels' => [],
                    'completed_lessons' => 0,
                    'total_lessons' => $this->getTotalLessons($track),
                ],
            ];
        }

        $progress = $this->calculateDetailedProgress($track, $user);

        return [
            'track' => $track->load(['levels.modules.lessons', 'instructor']),
            'enrollment' => $enrollment,
            'progress' => $progress,
        ];
    }

    /**
     * Validate track data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @throws ValidationException
     */
    private function validateTrackData(array $data, ?int $excludeId = null): void
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'slug' => 'nullable|string|max:255|unique:tracks,slug' . ($excludeId ? ",$excludeId" : ''),
            'is_premium' => 'boolean',
            'price' => 'nullable|numeric|min:0|max:9999.99',
            'difficulty_level' => 'in:beginner,intermediate,advanced',
            'estimated_duration' => 'nullable|integer|min:1',
        ];

        // Only require instructor_id for creation, not updates
        if ($excludeId === null) {
            $rules['instructor_id'] = 'required|exists:users,id';
        } else {
            $rules['instructor_id'] = 'sometimes|exists:users,id';
        }

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional business logic validation
        if (isset($data['is_premium']) && $data['is_premium'] && (!isset($data['price']) || $data['price'] <= 0)) {
            throw ValidationException::withMessages([
                'price' => 'Premium tracks must have a price greater than 0.',
            ]);
        }
    }

    /**
     * Validate hierarchy constraints.
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validateHierarchyConstraints(array $data): void
    {
        // Validate instructor exists and has instructor role
        if (isset($data['instructor_id'])) {
            $instructor = User::find($data['instructor_id']);
            if (!$instructor || $instructor->role !== 'instructor') {
                throw ValidationException::withMessages([
                    'instructor_id' => 'The selected instructor must have instructor role.',
                ]);
            }
        }

        // Validate estimated duration is reasonable (max 100 hours)
        if (isset($data['estimated_duration']) && $data['estimated_duration'] > 6000) {
            throw ValidationException::withMessages([
                'estimated_duration' => 'Estimated duration cannot exceed 6000 minutes (100 hours).',
            ]);
        }
    }

    /**
     * Validate track is ready for publishing.
     *
     * @param Track $track
     * @throws ValidationException
     */
    private function validateTrackForPublishing(Track $track): void
    {
        $errors = [];

        // Track must have at least one published level
        $publishedLevels = $track->levels()->where('is_published', true)->count();
        if ($publishedLevels === 0) {
            $errors['levels'] = 'Track must have at least one published level before publishing.';
        }

        // Each published level must have at least one published module
        $levelsWithoutModules = $track->levels()
            ->where('is_published', true)
            ->whereDoesntHave('modules', function ($query) {
                $query->where('is_published', true);
            })
            ->count();

        if ($levelsWithoutModules > 0) {
            $errors['modules'] = 'All published levels must have at least one published module.';
        }

        // Each published module must have at least one published lesson
        $modulesWithoutLessons = DB::table('modules')
            ->join('levels', 'modules.level_id', '=', 'levels.id')
            ->where('levels.track_id', $track->id)
            ->where('levels.is_published', true)
            ->where('modules.is_published', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('lessons')
                    ->whereColumn('lessons.module_id', 'modules.id')
                    ->where('lessons.is_published', true);
            })
            ->count();

        if ($modulesWithoutLessons > 0) {
            $errors['lessons'] = 'All published modules must have at least one published lesson.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Generate a unique slug for the track.
     *
     * @param string $title
     * @param int|null $excludeId
     * @return string
     */
    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists.
     *
     * @param string $slug
     * @param int|null $excludeId
     * @return bool
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = Track::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get total lessons count for a track.
     *
     * @param Track $track
     * @return int
     */
    private function getTotalLessons(Track $track): int
    {
        return DB::table('lessons')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('levels', 'modules.level_id', '=', 'levels.id')
            ->where('levels.track_id', $track->id)
            ->where('lessons.is_published', true)
            ->count();
    }

    /**
     * Calculate detailed progress for a user on a track.
     *
     * @param Track $track
     * @param User $user
     * @return array
     */
    private function calculateDetailedProgress(Track $track, User $user): array
    {
        $totalLessons = $this->getTotalLessons($track);
        $completedLessons = DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('levels', 'modules.level_id', '=', 'levels.id')
            ->where('levels.track_id', $track->id)
            ->where('lesson_progress.user_id', $user->id)
            ->whereNotNull('lesson_progress.completed_at')
            ->count();

        $overallPercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0;

        // Calculate level-wise progress
        $levels = [];
        foreach ($track->levels as $level) {
            $levelLessons = $this->getTotalLessonsForLevel($level);
            $levelCompletedLessons = $this->getCompletedLessonsForLevel($level, $user);
            $levelPercentage = $levelLessons > 0 ? round(($levelCompletedLessons / $levelLessons) * 100, 2) : 0;

            $levels[] = [
                'level_id' => $level->id,
                'title' => $level->title,
                'percentage' => $levelPercentage,
                'completed_lessons' => $levelCompletedLessons,
                'total_lessons' => $levelLessons,
            ];
        }

        return [
            'overall_percentage' => $overallPercentage,
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'levels' => $levels,
        ];
    }

    /**
     * Get total lessons for a level.
     *
     * @param $level
     * @return int
     */
    private function getTotalLessonsForLevel($level): int
    {
        return DB::table('lessons')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('modules.level_id', $level->id)
            ->where('lessons.is_published', true)
            ->count();
    }

    /**
     * Get completed lessons for a level by user.
     *
     * @param $level
     * @param User $user
     * @return int
     */
    private function getCompletedLessonsForLevel($level, User $user): int
    {
        return DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('modules.level_id', $level->id)
            ->where('lesson_progress.user_id', $user->id)
            ->whereNotNull('lesson_progress.completed_at')
            ->count();
    }
}
