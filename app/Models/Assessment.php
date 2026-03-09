<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessable_type',
        'assessable_id',
        'title',
        'description',
        'passing_score',
        'max_attempts',
        'time_limit',
        'is_required',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'passing_score' => 'decimal:2',
        'max_attempts' => 'integer',
        'time_limit' => 'integer',
        'is_required' => 'boolean',
    ];

    /**
     * Get the owning assessable model (lesson or module).
     */
    public function assessable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the questions for the assessment.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order_index');
    }

    /**
     * Get the attempts for the assessment.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /**
     * Get the total points for the assessment.
     */
    public function getTotalPoints(): int
    {
        return $this->questions()->sum('points');
    }

    /**
     * Check if a user has passed the assessment.
     */
    public function hasUserPassed(User $user): bool
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->where('passed', true)
            ->exists();
    }

    /**
     * Get the user's best attempt.
     */
    public function getBestAttempt(User $user): ?AssessmentAttempt
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('score')
            ->first();
    }

    /**
     * Get the course context for this assessment.
     * Returns the course if the assessment is directly attached to a course,
     * or if it's attached to a module that belongs to a course.
     */
    public function getCourseContext(): ?Course
    {
        // Direct course assessment
        if ($this->assessable_type === 'App\Models\Course') {
            return $this->assessable;
        }

        // Module assessment - check if module belongs to any courses
        if ($this->assessable_type === 'App\Models\Module') {
            return $this->assessable->courses()->first();
        }

        // Lesson assessment - check if lesson's module belongs to any courses
        if ($this->assessable_type === 'App\Models\Lesson') {
            return $this->assessable->module->courses()->first();
        }

        return null;
    }

    /**
     * Get the track context for this assessment.
     * Returns the track if the assessment is attached to content within a track.
     */
    public function getTrackContext(): ?Track
    {
        // Module assessment
        if ($this->assessable_type === 'App\Models\Module') {
            return $this->assessable->level?->track;
        }

        // Lesson assessment
        if ($this->assessable_type === 'App\Models\Lesson') {
            return $this->assessable->module->level?->track;
        }

        return null;
    }

    /**
     * Get the learning path context (either course or track).
     * Returns an array with type and model.
     */
    public function getLearningPathContext(): array
    {
        $course = $this->getCourseContext();
        if ($course) {
            return ['type' => 'course', 'model' => $course];
        }

        $track = $this->getTrackContext();
        if ($track) {
            return ['type' => 'track', 'model' => $track];
        }

        return ['type' => null, 'model' => null];
    }

    /**
     * Check if this assessment is in a course context.
     */
    public function isInCourseContext(): bool
    {
        return $this->getCourseContext() !== null;
    }

    /**
     * Check if this assessment is in a track context.
     */
    public function isInTrackContext(): bool
    {
        return $this->getTrackContext() !== null;
    }

    /**
     * Get assessment progress contribution for the learning path.
     * Returns the weight this assessment should have in overall progress calculation.
     */
    public function getProgressContribution(): float
    {
        // Required assessments have higher weight
        $baseWeight = $this->is_required ? 1.0 : 0.5;

        // Adjust weight based on total points
        $totalPoints = $this->getTotalPoints();
        $pointsWeight = $totalPoints > 0 ? min($totalPoints / 100, 2.0) : 1.0;

        return $baseWeight * $pointsWeight;
    }

    /**
     * Calculate assessment completion percentage for a user.
     * Returns percentage based on best attempt score.
     */
    public function getCompletionPercentage(User $user): float
    {
        $bestAttempt = $this->getBestAttempt($user);

        if (!$bestAttempt) {
            return 0.0;
        }

        $maxScore = $bestAttempt->max_score > 0 ? $bestAttempt->max_score : $this->getTotalPoints();

        if ($maxScore <= 0) {
            return $bestAttempt->passed ? 100.0 : 0.0;
        }

        return min(($bestAttempt->score / $maxScore) * 100, 100.0);
    }
}
