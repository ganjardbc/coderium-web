<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => 'string',
        ];
    }

    /**
     * Check if user is a learner
     */
    public function isLearner(): bool
    {
        return $this->role === 'learner';
    }

    /**
     * Check if user is an instructor
     */
    public function isInstructor(): bool
    {
        return $this->role === 'instructor';
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user has instructor permissions (instructor or admin)
     */
    public function hasInstructorPermissions(): bool
    {
        return in_array($this->role, ['instructor', 'admin']);
    }

    /**
     * Check if user has admin permissions
     */
    public function hasAdminPermissions(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user can manage classroom content
     */
    public function canManageClassroomContent(): bool
    {
        return $this->hasInstructorPermissions();
    }

    /**
     * Check if user can enroll in tracks
     */
    public function canEnrollInTracks(): bool
    {
        return true; // All users can enroll in tracks
    }

    /**
     * Check if user can enroll in courses
     */
    public function canEnrollInCourses(): bool
    {
        return true; // All users can enroll in courses
    }

    /**
     * Check if user can access classroom features
     */
    public function canAccessClassroom(): bool
    {
        return true; // All authenticated users can access classroom
    }

    /**
     * Achievements earned by this user.
     */
    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot(['earned_at', 'metadata'])
            ->withTimestamps();
    }

    /**
     * Certificates earned by this user.
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Track enrollments for this user.
     */
    public function trackEnrollments()
    {
        return $this->hasMany(TrackEnrollment::class);
    }

    /**
     * Course enrollments for this user.
     */
    public function courseEnrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Lesson progress for this user.
     */
    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Discussion posts by this user.
     */
    public function discussionPosts()
    {
        return $this->hasMany(DiscussionPost::class);
    }
}
