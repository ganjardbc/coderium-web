<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Track extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'slug',
        'is_premium',
        'price',
        'is_published',
        'difficulty_level',
        'estimated_duration',
        'instructor_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_premium' => 'boolean',
        'is_published' => 'boolean',
        'price' => 'decimal:2',
        'estimated_duration' => 'integer',
    ];

    /**
     * Get the instructor that owns the track.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get the levels for the track.
     */
    public function levels(): HasMany
    {
        return $this->hasMany(Level::class)->orderBy('order_index');
    }

    /**
     * Get the enrollments for the track.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(TrackEnrollment::class);
    }

    /**
     * Get the discussions for the track.
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class, 'discussable_id')
            ->where('discussable_type', self::class);
    }

    /**
     * Get the media for the track.
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable');
    }

    /**
     * Check if the track is free.
     */
    public function isFree(): bool
    {
        return !$this->is_premium || $this->price === null || $this->price == 0;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
