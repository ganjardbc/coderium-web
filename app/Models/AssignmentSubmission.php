<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_id',
        'user_id',
        'repository_url',
        'file_attachments',
        'submission_notes',
        'grade',
        'feedback',
        'submitted_at',
        'graded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_attachments' => 'array',
        'grade' => 'decimal:2',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    /**
     * Get the assignment that owns the submission.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Get the user that owns the submission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the submission is graded.
     */
    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }

    /**
     * Mark the submission as graded.
     */
    public function markGraded(float $grade, string $feedback = null): void
    {
        $this->update([
            'grade' => $grade,
            'feedback' => $feedback,
            'graded_at' => now(),
        ]);
    }
}
