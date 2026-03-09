<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $query = Module::with(['lessons'])
            ->withCount('lessons');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
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

        $modules = $query->orderBy('created_at', 'desc')->paginate(5);

        // Debug logging
        \Log::info('Modules Index - Total modules: ' . $modules->total());
        \Log::info('Modules Index - Current page data count: ' . count($modules->items()));

        return Inertia::render('admin/modules/Index', [
            'modules' => $modules,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/modules/Form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_duration' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'exists:media,id',
        ]);

        $module = Module::create([
            'title' => $request->title,
            'description' => $request->description,
            'estimated_duration' => $request->estimated_duration,
            'is_published' => $request->boolean('is_published'),
        ]);

        // Handle media if provided
        if ($request->filled('media_ids')) {
            $module->media()->sync($request->media_ids);
        }

        return redirect()->route('admin.modules.index')
            ->with('success', 'Module created successfully.');
    }

    public function show(Module $module)
    {
        $module->load([
            'lessons' => function ($query) {
                $query->orderBy('order_index');
            },
            'assessments' => function ($query) {
                $query->withCount(['questions', 'attempts']);
            }
        ])->loadCount(['lessons', 'assessments']);

        return Inertia::render('admin/modules/Show', [
            'module' => $module,
        ]);
    }

    public function edit(Module $module)
    {
        $module->load('media');

        return Inertia::render('admin/modules/Form', [
            'module' => $module,
        ]);
    }

    public function update(Request $request, Module $module)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_duration' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'exists:media,id',
        ]);

        $module->update([
            'title' => $request->title,
            'description' => $request->description,
            'estimated_duration' => $request->estimated_duration,
            'is_published' => $request->boolean('is_published'),
        ]);

        // Handle media if provided
        if ($request->has('media_ids')) {
            $module->media()->sync($request->media_ids ?? []);
        }

        return redirect()->route('admin.modules.index')
            ->with('success', 'Module updated successfully.');
    }

    public function destroy(Module $module)
    {
        $module->delete();

        return redirect()->route('admin.modules.index')
            ->with('success', 'Module deleted successfully.');
    }
}
