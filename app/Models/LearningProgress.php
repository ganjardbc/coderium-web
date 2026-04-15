<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LearningProgress extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'progressable_type',
        'progressable_id',
        'completion_percentage',
        'time_spent_minutes',
        'engagement_score',
        'last_accessed_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'completion_percentage' => 'decimal:2',
        'time_spent_minutes' => 'integer',
        'engagement_score' => 'decimal:2',
        'last_accessed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who owns this progress record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the progressable entity (track, course, module, or lesson).
     * Polymorphic relationship for flexible progress tracking.
     */
    public function progressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to get completed progress records.
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Scope to get progress records for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if the progress is completed.
     */
    public function isCompleted(): bool
    {
        return !is_null($this->completed_at) || $this->completion_percentage >= 100.00;
    }

    /**
     * Mark the progress as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'completion_percentage' => 100.00,
            'completed_at' => now(),
        ]);
    }
}
