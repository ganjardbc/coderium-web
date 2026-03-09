<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Course extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'slug',
        'is_active',
        'certificate_template_id',
        'estimated_duration',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'estimated_duration' => 'integer',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the modules assigned to this course.
     * Many-to-many relationship with proper ordering and pivot data.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'course_modules')
                    ->withPivot(['order', 'is_required'])
                    ->withTimestamps()
                    ->orderBy('course_modules.order');
    }

    /**
     * Get the enrollments for this course.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Get the certificates issued for this course.
     * Polymorphic relationship for dynamic certificate support.
     */
    public function certificates(): MorphMany
    {
        return $this->morphMany(Certificate::class, 'certifiable');
    }

    /**
     * Get the learning progress records for this course.
     * Polymorphic relationship for enhanced progress tracking.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(LearningProgress::class, 'progressable');
    }

    /**
     * Get the certificate template for this course.
     */
    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    /**
     * Get the assessments directly attached to this course.
     * Polymorphic relationship for course-level assessments.
     */
    public function assessments(): MorphMany
    {
        return $this->morphMany(Assessment::class, 'assessable');
    }
}
