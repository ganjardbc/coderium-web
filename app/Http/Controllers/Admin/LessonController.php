<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LessonController extends Controller
{
    public function index(Request $request)
    {
        $query = Lesson::with(['module']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Filter by lesson type
        if ($request->filled('type')) {
            $query->where('lesson_type', $request->get('type'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'published') {
                $query->where('is_published', true);
            } elseif ($status === 'draft') {
                $query->where('is_published', false);
            }
        }

        // Filter by module
        if ($request->filled('module')) {
            $moduleId = $request->get('module');
            if ($moduleId === 'standalone') {
                $query->whereNull('module_id');
            } else {
                $query->where('module_id', $moduleId);
            }
        }

        $lessons = $query->orderBy('created_at', 'desc')->paginate(5);

        return Inertia::render('admin/lessons/Index', [
            'lessons' => $lessons,
            'filters' => $request->only(['search', 'type', 'status', 'module']),
        ]);
    }

    public function create(Request $request)
    {
        $modules = Module::orderBy('title')->get();

        return Inertia::render('admin/lessons/Form', [
            'modules' => $modules,
            'selectedModuleId' => $request->get('module_id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'module_id' => 'nullable|exists:modules,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'lesson_type' => 'required|in:text,video,interactive',
            'estimated_duration' => 'required|integer|min:1',
            'is_published' => 'boolean',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'exists:media,id',
        ]);

        DB::transaction(function () use ($request) {
            // Get order index for the module (if assigned)
            $orderIndex = 1;
            if ($request->module_id) {
                $orderIndex = Lesson::where('module_id', $request->module_id)->max('order_index') + 1;
            }

            $lesson = Lesson::create([
                'module_id' => $request->module_id,
                'title' => $request->title,
                'content' => $request->content,
                'lesson_type' => $request->lesson_type,
                'order_index' => $orderIndex,
                'estimated_duration' => $request->estimated_duration,
                'is_published' => $request->boolean('is_published'),
            ]);

            // Handle media if provided
            if ($request->filled('media_ids')) {
                $lesson->media()->sync($request->media_ids);
            }
        });

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Lesson created successfully.');
    }

    public function show(Lesson $lesson)
    {
        $lesson->load(['module', 'media']);

        return Inertia::render('admin/lessons/Show', [
            'lesson' => $lesson,
        ]);
    }

    public function edit(Lesson $lesson)
    {
        $lesson->load(['module', 'media']);
        $modules = Module::orderBy('title')->get();

        return Inertia::render('admin/lessons/Form', [
            'lesson' => $lesson,
            'modules' => $modules,
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $request->validate([
            'module_id' => 'nullable|exists:modules,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'lesson_type' => 'required|in:text,video,interactive',
            'estimated_duration' => 'required|integer|min:1',
            'is_published' => 'boolean',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'exists:media,id',
        ]);

        DB::transaction(function () use ($request, $lesson) {
            // Handle module change
            $updateData = [
                'module_id' => $request->module_id,
                'title' => $request->title,
                'content' => $request->content,
                'lesson_type' => $request->lesson_type,
                'estimated_duration' => $request->estimated_duration,
                'is_published' => $request->boolean('is_published'),
            ];

            // If module changed, update order index
            if ($lesson->module_id !== $request->module_id) {
                if ($request->module_id) {
                    $updateData['order_index'] = Lesson::where('module_id', $request->module_id)->max('order_index') + 1;
                } else {
                    $updateData['order_index'] = 1;
                }
            }

            $lesson->update($updateData);

            // Handle media
            if ($request->has('media_ids')) {
                $lesson->media()->sync($request->media_ids ?? []);
            }
        });

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Lesson updated successfully.');
    }

    public function destroy(Lesson $lesson)
    {
        DB::transaction(function () use ($lesson) {
            // Detach media
            $lesson->media()->detach();

            // Delete the lesson
            $lesson->delete();

            // Reorder remaining lessons in the module
            if ($lesson->module_id) {
                Lesson::where('module_id', $lesson->module_id)
                    ->where('order_index', '>', $lesson->order_index)
                    ->decrement('order_index');
            }
        });

        return redirect()->route('admin.lessons.index')
            ->with('success', 'Lesson deleted successfully.');
    }
}
