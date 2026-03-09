<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'enrolled_at',
        'completed_at',
        'progress_percentage',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_percentage' => 'decimal:2',
    ];

    /**
     * Get the user who is enrolled.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course for this enrollment.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Check if the enrollment is completed.
     */
    public function isCompleted(): bool
    {
        return !is_null($this->completed_at) || $this->progress_percentage >= 100.00;
    }

    /**
     * Mark the enrollment as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'progress_percentage' => 100.00,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get enrollment data in the same format as track enrollments.
     * Provides backward compatibility for API responses.
     */
    public function toTrackEnrollmentFormat(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'track_id' => null, // Course enrollments don't have track_id
            'course_id' => $this->course_id,
            'enrolled_at' => $this->enrolled_at,
            'completed_at' => $this->completed_at,
            'progress_percentage' => $this->progress_percentage,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'type' => 'course', // Identifier for enrollment type
        ];
    }

    /**
     * Scope to get active enrollments (not completed).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('completed_at');
    }

    /**
     * Scope to get completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }
}
