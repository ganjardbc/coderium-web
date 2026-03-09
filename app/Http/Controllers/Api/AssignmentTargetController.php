<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AssignmentTargetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Assignment targets could be courses, tracks, levels, or modules
        // This is a simplified implementation - adjust based on your needs

        $targets = [];

        // Add courses as targets
        $courses = \App\Models\Course::select('id', 'title', 'slug')
            ->where('is_active', true)
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'type' => 'course',
                    'title' => $course->title,
                    'slug' => $course->slug,
                ];
            });

        $targets = array_merge($targets, $courses->toArray());

        // Add tracks as targets
        $tracks = \App\Models\Track::select('id', 'title', 'slug')
            ->where('is_published', true)
            ->get()
            ->map(function ($track) {
                return [
                    'id' => $track->id,
                    'type' => 'track',
                    'title' => $track->title,
                    'slug' => $track->slug,
                ];
            });

        $targets = array_merge($targets, $tracks->toArray());

        // Add levels as targets
        $levels = \App\Models\Level::with('track')
            ->get()
            ->map(function ($level) {
                return [
                    'id' => $level->id,
                    'type' => 'level',
                    'title' => $level->title,
                    'track_title' => $level->track->title ?? '',
                ];
            });

        $targets = array_merge($targets, $levels->toArray());

        return response()->json($targets);
    }
}
