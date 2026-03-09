<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // For now, we'll treat published courses as templates
        // You might want to create a separate CourseTemplate model later

        $templates = Course::select('id', 'title', 'description', 'slug', 'created_at')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->title,
                    'description' => $course->description,
                    'slug' => $course->slug,
                    'created_at' => $course->created_at,
                    'modules_count' => $course->modules()->count(),
                ];
            });

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'courseId' => 'required|exists:courses,id',
            'description' => 'nullable|string',
        ]);

        $course = Course::findOrFail($validated['courseId']);

        // Create a template based on the course
        // For now, we'll just return the course data as a template
        // You might want to implement actual template creation logic

        $template = [
            'id' => $course->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $course->description,
            'slug' => $course->slug,
            'created_at' => now(),
            'modules_count' => $course->modules()->count(),
            'source_course_id' => $course->id,
        ];

        return response()->json($template, 201);
    }
}
