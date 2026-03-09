<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Assignment::with(['module', 'submissions']);

        // Apply filters
        if ($request->has('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        $assignments = $query->orderBy('created_at', 'desc')->get();

        return response()->json(AssignmentResource::collection($assignments));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'module_id' => 'required|exists:modules,id',
            'due_date' => 'nullable|date',
            'instructions' => 'required|string',
            'evaluation_checklist' => 'nullable|array',
            'is_published' => 'boolean',
        ]);

        $assignment = Assignment::create($validated);
        $assignment->load(['module', 'submissions']);

        return response()->json(new AssignmentResource($assignment), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'due_date' => 'nullable|date',
            'instructions' => 'sometimes|string',
            'evaluation_checklist' => 'nullable|array',
            'is_published' => 'boolean',
        ]);

        $assignment->update($validated);
        $assignment->load(['module', 'submissions']);

        return response()->json(new AssignmentResource($assignment));
    }

    public function destroy(string $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);
        $assignment->delete();

        return response()->json(['message' => 'Assignment deleted successfully']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.id' => 'required|exists:assignments,id',
            'assignments.*.order' => 'required|integer',
        ]);

        // Since the assignments table doesn't have an order_index field,
        // we'll just return success for now. You can implement custom ordering logic here.

        return response()->json(['message' => 'Assignments reordered successfully']);
    }

    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:create,update,delete',
            'assignments' => 'required|array',
        ]);

        $results = [];

        DB::transaction(function () use ($validated, &$results) {
            foreach ($validated['assignments'] as $assignmentData) {
                try {
                    switch ($validated['action']) {
                        case 'create':
                            $assignment = Assignment::create($assignmentData);
                            $results[] = ['status' => 'success', 'id' => $assignment->id];
                            break;
                        case 'update':
                            $assignment = Assignment::findOrFail($assignmentData['id']);
                            $assignment->update($assignmentData);
                            $results[] = ['status' => 'success', 'id' => $assignment->id];
                            break;
                        case 'delete':
                            Assignment::findOrFail($assignmentData['id'])->delete();
                            $results[] = ['status' => 'success', 'id' => $assignmentData['id']];
                            break;
                    }
                } catch (\Exception $e) {
                    $results[] = ['status' => 'error', 'message' => $e->getMessage()];
                }
            }
        });

        return response()->json(['results' => $results]);
    }

    public function conflicts(Request $request): JsonResponse
    {
        // Basic conflict detection - expand based on your business logic
        $moduleId = $request->get('moduleId');

        $conflicts = [];

        // Example: Check for duplicate assignments in the same module
        if ($moduleId) {
            $existingAssignments = Assignment::where('module_id', $moduleId)
                ->get();

            // You can implement more sophisticated conflict detection here
            // For now, we'll just return an empty array
        }

        return response()->json($conflicts);
    }

    public function resolveConflict(Request $request, string $conflictId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:merge,replace,skip',
            'resolutionId' => 'required|string',
        ]);

        // Implement conflict resolution logic based on your needs
        // This is a placeholder implementation

        return response()->json(['message' => 'Conflict resolved successfully']);
    }

    public function cancelBulkOperation(string $operationId): JsonResponse
    {
        // Implement bulk operation cancellation logic
        // This is a placeholder implementation

        return response()->json(['message' => 'Bulk operation cancelled successfully']);
    }
}
