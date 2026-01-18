<?php

namespace App\Providers;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Module;
use App\Models\Track;
use App\Policies\AssessmentPolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\LessonPolicy;
use App\Policies\LevelPolicy;
use App\Policies\ModulePolicy;
use App\Policies\TrackPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Track::class => TrackPolicy::class,
        Level::class => LevelPolicy::class,
        Module::class => ModulePolicy::class,
        Lesson::class => LessonPolicy::class,
        Assessment::class => AssessmentPolicy::class,
        Assignment::class => AssignmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define additional gates if needed
        Gate::define('manage-classroom', function ($user) {
            return $user->canManageClassroomContent();
        });

        Gate::define('access-classroom', function ($user) {
            return $user->canAccessClassroom();
        });

        Gate::define('enroll-in-tracks', function ($user) {
            return $user->canEnrollInTracks();
        });
    }
}
