<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'lesson_id',
        'completed_at',
        'time_spent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'completed_at' => 'datetime',
        'time_spent' => 'integer',
    ];

    /**
     * Get the user that owns the progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the lesson that owns the progress.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Check if the lesson is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Mark the lesson as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'completed_at' => now(),
        ]);
    }

    /**
     * Get the corresponding learning progress record.
     * Backward compatibility method to bridge to new progress system.
     */
    public function toLearningProgress(): ?LearningProgress
    {
        return LearningProgress::where([
            'user_id' => $this->user_id,
            'progressable_type' => 'App\\Models\\Lesson',
            'progressable_id' => $this->lesson_id,
        ])->first();
    }

    /**
     * Convert time spent from seconds to minutes for compatibility.
     */
    public function getTimeSpentMinutesAttribute(): int
    {
        return intval($this->time_spent / 60);
    }

    /**
     * Get completion percentage (100% if completed, 0% if not).
     */
    public function getCompletionPercentageAttribute(): float
    {
        return $this->isCompleted() ? 100.00 : 0.00;
    }
}
