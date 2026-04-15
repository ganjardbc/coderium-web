<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\TrackEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->get('userId', auth()->id());

        $enrollments = [];

        // Get course enrollments
        $courseEnrollments = CourseEnrollment::with(['course'])
            ->where('user_id', $userId)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'type' => 'course',
                    'title' => $enrollment->course->title,
                    'slug' => $enrollment->course->slug,
                    'enrolled_at' => $enrollment->created_at,
                    'progress' => $enrollment->progress_percentage ?? 0,
                    'status' => $enrollment->status ?? 'active',
                ];
            });

        $enrollments = array_merge($enrollments, $courseEnrollments->toArray());

        // Get track enrollments
        $trackEnrollments = TrackEnrollment::with(['track'])
            ->where('user_id', $userId)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'type' => 'track',
                    'title' => $enrollment->track->title,
                    'slug' => $enrollment->track->slug,
                    'enrolled_at' => $enrollment->created_at,
                    'progress' => $enrollment->progress_percentage ?? 0,
                    'status' => $enrollment->status ?? 'active',
                ];
            });

        $enrollments = array_merge($enrollments, $trackEnrollments->toArray());

        // Sort by enrollment date
        usort($enrollments, function ($a, $b) {
            return strtotime($b['enrolled_at']) - strtotime($a['enrolled_at']);
        });

        return response()->json($enrollments);
    }
}
