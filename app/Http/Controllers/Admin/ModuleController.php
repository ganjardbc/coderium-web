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
            'is_published' => 'boolean',
        ]);

        Module::create($validated);

        return redirect()->route('admin.classroom.modules.index')
            ->with('success', 'Module created successfully.');
    }

    public function edit(Module $module)
    {
        $level = $module->level()->with('track')->first();
        $maxOrderIndex = Module::where('level_id', $module->level_id)->max('order_index') ?? 0;

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
            'is_published' => 'boolean',
        ]);

        $module->update($validated);

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
