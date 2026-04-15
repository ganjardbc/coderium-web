<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Level extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'track_id',
        'title',
        'description',
        'difficulty',
        'order_index',
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
    ];

    /**
     * Get the track that owns the level.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * Get all modules for the level (both legacy direct and new flexible assignments).
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order_index');
    }

    /**
     * Get all modules including both direct and pivot assignments.
     */
    public function allModules()
    {
        // Get directly assigned modules (legacy)
        $directModules = $this->modules;

        // Get pivot assigned modules (new system)
        $pivotModules = $this->assignedModules;

        // Merge and remove duplicates
        return $directModules->merge($pivotModules)->unique('id');
    }

    /**
     * Get the modules assigned to this level through the pivot table (new flexible system).
     */
    public function assignedModules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'level_modules')
                    ->withPivot(['order', 'is_required'])
                    ->withTimestamps()
                    ->orderBy('order');
    }

    /**
     * Get all lessons through modules.
     */
    public function lessons(): HasMany
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }
}
