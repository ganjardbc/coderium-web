<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackEnrollment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'track_id',
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
     * Get the user that owns the enrollment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the track that owns the enrollment.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * Check if the enrollment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Mark the enrollment as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'completed_at' => now(),
            'progress_percentage' => 100.00,
        ]);
    }

    /**
     * Get enrollment data in a unified format.
     * Provides compatibility for unified enrollment APIs.
     */
    public function toUnifiedEnrollmentFormat(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'track_id' => $this->track_id,
            'course_id' => null, // Track enrollments don't have course_id
            'enrolled_at' => $this->enrolled_at,
            'completed_at' => $this->completed_at,
            'progress_percentage' => $this->progress_percentage,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'type' => 'track', // Identifier for enrollment type
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
