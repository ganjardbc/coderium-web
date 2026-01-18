<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Level;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::with(['level.track'])
            ->withCount(['lessons'])
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/classroom/ModuleIndex', [
            'modules' => $modules,
        ]);
    }

    public function create(Request $request)
    {
        $levelId = $request->get('level_id');

        if (!$levelId) {
            // Show level selection page
            $levels = Level::with('track')->orderBy('id')->get();
            return Inertia::render('admin/classroom/ModuleCreate', [
                'levels' => $levels,
            ]);
        }

        // Show module creation form
        $level = Level::with('track')->findOrFail($levelId);
        $maxOrderIndex = Module::where('level_id', $levelId)->max('order_index') ?? 0;

        return Inertia::render('admin/classroom/ModuleEditor', [
            'level' => $level,
            'maxOrderIndex' => $maxOrderIndex,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'order_index' => 'required|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:1',
            'is_published' => 'boolean',
            'media' => 'nullable|array',
            'media.*.id' => 'required|exists:media,id',
        ]);

        // Extract media data before creating module
        $mediaData = $validated['media'] ?? [];
        unset($validated['media']);

        $module = Module::create($validated);

        // Attach media relationships
        if (!empty($mediaData)) {
            $mediaIds = collect($mediaData)->pluck('id')->toArray();
            $module->media()->attach($mediaIds);
        }

        return redirect()->route('admin.classroom.modules.index')
            ->with('success', 'Module created successfully.');
    }

    public function edit(Module $module)
    {
        $level = $module->level()->with('track')->first();
        $maxOrderIndex = Module::where('level_id', $module->level_id)->max('order_index') ?? 0;

        // Load the module with its media relationship
        $module->load('media');

        return Inertia::render('admin/classroom/ModuleEditor', [
            'module' => $module,
            'level' => $level,
            'maxOrderIndex' => $maxOrderIndex,
        ]);
    }

    public function update(Request $request, Module $module)
    {
        $validated = $request->validate([
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'order_index' => 'required|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:1',
            'is_published' => 'boolean',
            'media' => 'nullable|array',
            'media.*.id' => 'required|exists:media,id',
        ]);

        // Extract media data before updating module
        $mediaData = $validated['media'] ?? [];
        unset($validated['media']);

        $module->update($validated);

        // Sync media relationships
        if (isset($mediaData)) {
            $mediaIds = collect($mediaData)->pluck('id')->toArray();
            $module->media()->sync($mediaIds);
        }

        return redirect()->route('admin.classroom.modules.index')
            ->with('success', 'Module updated successfully.');
    }

    public function destroy(Module $module)
    {
        $module->delete();

        return redirect()->route('admin.classroom.modules.index')
            ->with('success', 'Module deleted successfully.');
    }
}
