<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'icon',
        'color',
        'criteria',
        'points',
        'is_active',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Users who have earned this achievement.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot(['earned_at', 'metadata'])
            ->withTimestamps();
    }

    /**
     * Check if a user has earned this achievement.
     */
    public function isEarnedBy(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Get achievement types.
     */
    public static function getTypes(): array
    {
        return [
            'first_lesson' => 'First Lesson Completed',
            'first_module' => 'First Module Completed',
            'first_level' => 'First Level Completed',
            'track_completion' => 'Track Completed',
            'streak_7' => '7-Day Learning Streak',
            'streak_30' => '30-Day Learning Streak',
            'fast_learner' => 'Fast Learner',
            'perfectionist' => 'Perfect Assessment Scores',
            'explorer' => 'Multiple Tracks Enrolled',
        ];
    }
}
