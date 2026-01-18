<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LessonController extends Controller
{
    public function index()
    {
        $lessons = Lesson::with(['module.level.track'])
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/classroom/LessonIndex', [
            'lessons' => $lessons,
        ]);
    }

    public function create(Request $request)
    {
        $moduleId = $request->get('module_id');

        if (!$moduleId) {
            // Show module selection page
            $modules = Module::with('level.track')->orderBy('id')->get();
            return Inertia::render('admin/classroom/LessonCreate', [
                'modules' => $modules,
            ]);
        }

        // Show lesson creation form
        $module = Module::with('level.track')->findOrFail($moduleId);
        $maxOrderIndex = Lesson::where('module_id', $moduleId)->max('order_index') ?? 0;

        return Inertia::render('admin/classroom/LessonEditor', [
            'module' => $module,
            'maxOrderIndex' => $maxOrderIndex,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'lesson_type' => 'required|in:text,video,interactive',
            'order_index' => 'required|integer|min:0',
            'estimated_duration' => 'required|integer|min:1',
            'is_published' => 'boolean',
            'media' => 'array',
            'media.*' => 'exists:media,id',
        ]);

        $lesson = Lesson::create($validated);

        // Attach media if provided
        if (!empty($validated['media'])) {
            $lesson->media()->attach($validated['media']);
        }

        return redirect()->route('admin.classroom.lessons.index')
            ->with('success', 'Lesson created successfully.');
    }

    public function edit(Lesson $lesson)
    {
        $module = $lesson->module()->with('level.track')->first();
        $maxOrderIndex = Lesson::where('module_id', $lesson->module_id)->max('order_index') ?? 0;

        return Inertia::render('admin/classroom/LessonEditor', [
            'lesson' => $lesson->load('media'),
            'module' => $module,
            'maxOrderIndex' => $maxOrderIndex,
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'lesson_type' => 'required|in:text,video,interactive',
            'order_index' => 'required|integer|min:0',
            'estimated_duration' => 'required|integer|min:1',
            'is_published' => 'boolean',
            'media' => 'array',
            'media.*' => 'exists:media,id',
        ]);

        $lesson->update($validated);

        // Sync media if provided
        if (isset($validated['media'])) {
            $lesson->media()->sync($validated['media']);
        }

        return redirect()->route('admin.classroom.lessons.index')
            ->with('success', 'Lesson updated successfully.');
    }

    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return redirect()->route('admin.classroom.lessons.index')
            ->with('success', 'Lesson deleted successfully.');
    }
}
