<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackResource;
use App\Models\Track;
use App\Services\TrackService;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackController extends Controller
{
    public function __construct(
        private TrackService $trackService,
        private EnrollmentService $enrollmentService
    ) {}

    /**
     * Display a listing of tracks.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Track::query();

        // For non-instructors, only show published tracks
        if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
            $query->where('is_published', true);
        }

        // Filter by difficulty level
        if ($request->has('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        // Filter by premium status
        if ($request->has('is_premium')) {
            $query->where('is_premium', $request->boolean('is_premium'));
        }

        // Filter by instructor
        if ($request->has('instructor_id')) {
            $query->where('instructor_id', $request->instructor_id);
        }

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Load relationships
        $query->with(['instructor', 'levels'])
              ->withCount(['enrollments', 'levels']);

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        if (in_array($sortBy, ['created_at', 'title', 'difficulty_level', 'estimated_duration'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $tracks = $query->paginate($request->get('per_page', 12));

        return TrackResource::collection($tracks);
    }

    /**
     * Store a newly created track.
     */
    public function store(Request $request): TrackResource
    {
        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to create tracks.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'slug' => 'nullable|string|max:255|unique:tracks,slug',
            'is_premium' => 'boolean',
            'price' => 'nullable|numeric|min:0|max:9999.99',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'estimated_duration' => 'nullable|integer|min:1',
            'is_published' => 'boolean',
        ]);

        // Set instructor_id to current user
        $validated['instructor_id'] = $request->user()->id;

        try {
            $track = $this->trackService->createTrack($validated);
            return new TrackResource($track->load(['instructor', 'levels']));
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    /**
     * Display the specified track.
     */
    public function show(Request $request, string $slug): TrackResource
    {
        $track = Track::where('slug', $slug)
            ->with(['instructor', 'levels.modules.lessons', 'media'])
            ->withCount(['enrollments', 'levels'])
            ->firstOrFail();

        // Check access permissions
        if (!$track->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }

        // If user is authenticated, get progress data
        if ($request->user()) {
            $trackData = $this->trackService->getTrackWithProgress($track, $request->user());
            $track->enrollment = $trackData['enrollment'];
            $track->progress = $trackData['progress'];
        }

        return new TrackResource($track);
    }

    /**
     * Update the specified track.
     */
    public function update(Request $request, string $slug): TrackResource
    {
        $track = Track::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->canManageClassroomContent() ||
            ($request->user()->isInstructor() && $track->instructor_id !== $request->user()->id)) {
            abort(403, 'Unauthorized to update this track.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'slug' => 'nullable|string|max:255|unique:tracks,slug,' . $track->id,
            'is_premium' => 'boolean',
            'price' => 'nullable|numeric|min:0|max:9999.99',
            'difficulty_level' => 'sometimes|required|in:beginner,intermediate,advanced',
            'estimated_duration' => 'nullable|integer|min:1',
            'is_published' => 'boolean',
        ]);

        try {
            $track = $this->trackService->updateTrack($track, $validated);
            return new TrackResource($track->load(['instructor', 'levels']));
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    /**
     * Remove the specified track.
     */
    public function destroy(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $track = Track::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->canManageClassroomContent() ||
            ($request->user()->isInstructor() && $track->instructor_id !== $request->user()->id)) {
            abort(403, 'Unauthorized to delete this track.');
        }

        // Check if track has enrollments
        if ($track->enrollments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete track with existing enrollments.',
            ], 422);
        }

        $track->delete();

        return response()->json([
            'message' => 'Track deleted successfully.',
        ], 200);
    }

    /**
     * Enroll the authenticated user in a track.
     */
    public function enroll(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $track = Track::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required for enrollment.');
        }

        try {
            $enrollment = $this->enrollmentService->enrollUser($user, $track);

            return response()->json([
                'message' => 'Successfully enrolled in track.',
                'enrollment' => [
                    'id' => $enrollment->id,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'progress_percentage' => $enrollment->progress_percentage,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Enrollment failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Unenroll the authenticated user from a track.
     */
    public function unenroll(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $track = Track::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        try {
            $this->enrollmentService->unenrollUser($user, $track);

            return response()->json([
                'message' => 'Successfully unenrolled from track.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Unenrollment failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get enrollment statistics for a track (instructors only).
     */
    public function enrollmentStats(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $track = Track::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->hasInstructorPermissions() ||
            ($request->user()->isInstructor() && $track->instructor_id !== $request->user()->id)) {
            abort(403, 'Unauthorized to view enrollment statistics.');
        }

        $stats = $this->enrollmentService->getEnrollmentStatistics($track);

        return response()->json([
            'track' => [
                'id' => $track->id,
                'title' => $track->title,
                'slug' => $track->slug,
            ],
            'statistics' => $stats,
        ], 200);
    }

    /**
     * Publish a track (instructors only).
     */
    public function publish(Request $request, string $slug): TrackResource
    {
        $track = Track::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->canManageClassroomContent() ||
            ($request->user()->isInstructor() && $track->instructor_id !== $request->user()->id)) {
            abort(403, 'Unauthorized to publish this track.');
        }

        try {
            $this->trackService->publishTrack($track);
            return new TrackResource($track->fresh()->load(['instructor', 'levels']));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Publishing failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
