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
}
