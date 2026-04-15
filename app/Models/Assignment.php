<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'module_id',
        'title',
        'description',
        'instructions',
        'evaluation_checklist',
        'due_date',
        'is_published',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'evaluation_checklist' => 'array',
        'due_date' => 'datetime',
        'is_published' => 'boolean',
    ];

    /**
     * Get the module that owns the assignment.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the submissions for the assignment.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * Check if the assignment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }

    /**
     * Get the submission for a specific user.
     */
    public function getSubmissionForUser(User $user): ?AssignmentSubmission
    {
        return $this->submissions()->where('user_id', $user->id)->first();
    }

    /**
     * Check if a user has submitted the assignment.
     */
    public function hasUserSubmitted(User $user): bool
    {
        return $this->submissions()->where('user_id', $user->id)->exists();
    }
}
