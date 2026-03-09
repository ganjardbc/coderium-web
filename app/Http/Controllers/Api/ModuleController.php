<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Module::with(['level', 'lessons']);

        // Apply filters
        if ($request->has('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        $modules = $query->orderBy('order_index')->get();

        return response()->json(ModuleResource::collection($modules));
    }

    public function show(string $id): JsonResponse
    {
        $module = Module::with(['level', 'lessons', 'assignments'])
            ->findOrFail($id);

        return response()->json(new ModuleResource($module));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        $modules = Module::with(['level'])
            ->where('title', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->orderBy('title')
            ->limit(50)
            ->get();

        return response()->json(ModuleResource::collection($modules));
    }

    public function analytics(string $id): JsonResponse
    {
        $module = Module::findOrFail($id);

        // Basic analytics - you can expand this based on your needs
        $analytics = [
            'total_lessons' => $module->lessons()->count(),
            'completed_lessons' => $module->lessons()
                ->whereHas('progress', function ($query) {
                    $query->where('completed', true);
                })
                ->count(),
            'total_assignments' => $module->assignments()->count(),
            'completion_rate' => 0, // Calculate based on your logic
        ];

        return response()->json($analytics);
    }
}
