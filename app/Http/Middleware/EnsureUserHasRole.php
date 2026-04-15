<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            if ($this->userHasRole($user, $role)) {
                return $next($request);
            }
        }

        // If user doesn't have required role, return 403
        abort(403, 'Insufficient permissions to access this resource.');
    }

    /**
     * Check if user has the specified role
     */
    private function userHasRole($user, string $role): bool
    {
        // Admin users can access all routes
        if ($user->isAdmin()) {
            return true;
        }

        switch ($role) {
            case 'learner':
                return $user->isLearner();
            case 'instructor':
                return $user->isInstructor();
            case 'admin':
                return $user->isAdmin();
            case 'instructor_or_admin':
                return $user->hasInstructorPermissions();
            case 'admin_only':
                return $user->hasAdminPermissions();
            default:
                return false;
        }
    }
}
