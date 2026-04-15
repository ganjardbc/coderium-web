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
            ->paginate(5);

        return Inertia::render('admin/levels/index', [
            'levels' => $levels,
        ]);
    }

    public function create(Request $request)
    {
        $trackId = $request->get('track_id');

        if (!$trackId) {
            // Show track selection page
            $tracks = Track::orderBy('title')->get();
            return Inertia::render('admin/levels/create', [
                'tracks' => $tracks,
            ]);
        }

        // Show level creation form
        $track = Track::findOrFail($trackId);
        $maxOrderIndex = Level::where('track_id', $trackId)->max('order_index') ?? 0;

        return Inertia::render('admin/levels/create', [
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

        $level = Level::create($validated);

        return redirect()->route('admin.tracks.levels', $level->track->slug)
            ->with('success', 'Level created successfully.');
    }

    public function show(Level $level)
    {
        $level->load(['track', 'modules' => function ($query) {
            $query->withCount('lessons')->orderBy('order_index');
        }]);

        return Inertia::render('admin/levels/show', [
            'level' => $level,
        ]);
    }

    public function edit(Level $level)
    {
        $track = $level->track;
        $maxOrderIndex = Level::where('track_id', $level->track_id)->max('order_index') ?? 0;

        return Inertia::render('admin/levels/edit', [
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

        return redirect()->route('admin.tracks.levels', $level->track->slug)
            ->with('success', 'Level updated successfully.');
    }

    public function destroy(Level $level)
    {
        $trackSlug = $level->track->slug;
        $level->delete();

        return redirect()->route('admin.tracks.levels', $trackSlug)
            ->with('success', 'Level deleted successfully.');
    }

    public function move(Request $request, Level $level)
    {
        $direction = $request->input('direction');

        if ($direction === 'up') {
            $swapLevel = Level::where('track_id', $level->track_id)
                ->where('order_index', '<', $level->order_index)
                ->orderBy('order_index', 'desc')
                ->first();
        } else {
            $swapLevel = Level::where('track_id', $level->track_id)
                ->where('order_index', '>', $level->order_index)
                ->orderBy('order_index', 'asc')
                ->first();
        }

        if ($swapLevel) {
            $tempOrder = $level->order_index;
            $level->order_index = $swapLevel->order_index;
            $swapLevel->order_index = $tempOrder;

            $level->save();
            $swapLevel->save();
        }

        return redirect()->route('admin.tracks.levels', $level->track->slug)
            ->with('success', 'Level order updated successfully.');
    }
}
