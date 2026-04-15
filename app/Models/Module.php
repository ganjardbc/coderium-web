<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'level_id',
        'title',
        'description',
        'order_index',
        'estimated_duration',
        'is_published',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_published' => 'boolean',
        'order_index' => 'integer',
        'estimated_duration' => 'integer',
    ];

    /**
     * Get the level that owns the module.
     * Maintained for backward compatibility.
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * Get the levels that this module is assigned to.
     * New flexible many-to-many relationship.
     */
    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'level_modules')
                    ->withPivot(['order', 'is_required'])
                    ->withTimestamps()
                    ->orderBy('order');
    }

    /**
     * Get the courses that this module is assigned to.
     * New flexible many-to-many relationship.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_modules')
                    ->withPivot(['order', 'is_required'])
                    ->withTimestamps()
                    ->orderBy('order');
    }

    /**
     * Get the lessons for the module.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order_index');
    }

    /**
     * Get the assessments for the module.
     */
    public function assessments(): MorphMany
    {
        return $this->morphMany(Assessment::class, 'assessable');
    }

    /**
     * Get the assignments for the module.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get the discussions for the module.
     */
    public function discussions(): MorphMany
    {
        return $this->morphMany(Discussion::class, 'discussable');
    }

    /**
     * Get the media for the module.
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable');
    }

    /**
     * Get all levels this module is assigned to (including the primary level).
     * Backward compatibility method that combines old and new relationships.
     */
    public function getAllLevels()
    {
        $levels = collect();

        // Add the primary level (backward compatibility)
        if ($this->level_id && $this->level) {
            $levels->push($this->level);
        }

        // Add levels from the new many-to-many relationship
        $assignedLevels = $this->levels()->get();
        foreach ($assignedLevels as $level) {
            if (!$levels->contains('id', $level->id)) {
                $levels->push($level);
            }
        }

        return $levels;
    }

    /**
     * Check if module is assigned to a specific level.
     * Works with both old and new relationship systems.
     */
    public function isAssignedToLevel($levelId): bool
    {
        // Check primary level (backward compatibility)
        if ($this->level_id == $levelId) {
            return true;
        }

        // Check many-to-many assignments
        return $this->levels()->where('levels.id', $levelId)->exists();
    }

    /**
     * Get the order for this module in a specific level.
     */
    public function getOrderInLevel($levelId): int
    {
        // Check if this is the primary level
        if ($this->level_id == $levelId) {
            return $this->order_index ?? 0;
        }

        // Check many-to-many pivot data
        $pivot = $this->levels()->where('levels.id', $levelId)->first();
        return $pivot ? $pivot->pivot->order : 0;
    }

    /**
     * Check if module is required in a specific level.
     */
    public function isRequiredInLevel($levelId): bool
    {
        // Primary level modules are considered required by default
        if ($this->level_id == $levelId) {
            return true;
        }

        // Check many-to-many pivot data
        $pivot = $this->levels()->where('levels.id', $levelId)->first();
        return $pivot ? $pivot->pivot->is_required : true;
    }
}
