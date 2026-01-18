<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\Track;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LevelController extends Controller
{
    public function index()
    {
        $levels = Level::with(['track'])
            ->withCount(['modules'])
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/classroom/LevelIndex', [
            'levels' => $levels,
        ]);
    }

    public function create(Request $request)
    {
        $trackId = $request->get('track_id');

        if (!$trackId) {
            // Show track selection page
            $tracks = Track::orderBy('title')->get();
            return Inertia::render('admin/classroom/LevelCreate', [
                'tracks' => $tracks,
            ]);
        }

        // Show level creation form
        $track = Track::findOrFail($trackId);
        $maxOrderIndex = Level::where('track_id', $trackId)->max('order_index') ?? 0;

        return Inertia::render('admin/classroom/LevelEditor', [
            'track' => $track,
            'maxOrderIndex' => $maxOrderIndex,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'track_id' => 'required|exists:tracks,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'difficulty' => 'required|in:beginner,intermediate,advanced',
            'order_index' => 'required|integer|min:0',
            'is_published' => 'boolean',
        ]);

        Level::create($validated);

        return redirect()->route('admin.classroom.levels.index')
            ->with('success', 'Level created successfully.');
    }

    public function edit(Level $level)
    {
        $track = $level->track;
        $maxOrderIndex = Level::where('track_id', $level->track_id)->max('order_index') ?? 0;

        return Inertia::render('admin/classroom/LevelEditor', [
            'level' => $level,
            'track' => $track,
            'maxOrderIndex' => $maxOrderIndex,
        ]);
    }

    public function update(Request $request, Level $level)
    {
        $validated = $request->validate([
            'track_id' => 'required|exists:tracks,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'difficulty' => 'required|in:beginner,intermediate,advanced',
            'order_index' => 'required|integer|min:0',
            'is_published' => 'boolean',
        ]);

        $level->update($validated);

        return redirect()->route('admin.classroom.levels.index')
            ->with('success', 'Level updated successfully.');
    }

    public function destroy(Level $level)
    {
        $level->delete();

        return redirect()->route('admin.classroom.levels.index')
            ->with('success', 'Level deleted successfully.');
    }
}
