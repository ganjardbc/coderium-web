<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TrackController extends Controller
{
    public function index()
    {
        $tracks = Track::withCount(['levels', 'enrollments'])
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/classroom/TrackIndex', [
            'tracks' => $tracks,
        ]);
    }

    public function create()
    {
        $instructors = User::where('role', 'instructor')
            ->orWhere('role', 'admin')
            ->select('id', 'name', 'email')
            ->get();

        return Inertia::render('admin/classroom/TrackEditor', [
            'instructors' => $instructors,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'slug' => 'required|string|max:255|unique:tracks,slug',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'estimated_duration' => 'nullable|integer|min:1',
            'instructor_id' => 'nullable|exists:users,id',
            'is_premium' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'is_published' => 'boolean',
            'media' => 'nullable|array',
            'media.*.id' => 'required|exists:media,id',
        ]);

        // Ensure slug is properly formatted
        $validated['slug'] = Str::slug($validated['slug']);

        // Extract media data before creating track
        $mediaData = $validated['media'] ?? [];
        unset($validated['media']);

        $track = Track::create($validated);

        // Attach media relationships
        if (!empty($mediaData)) {
            $mediaIds = collect($mediaData)->pluck('id')->toArray();
            $track->media()->attach($mediaIds);
        }

        return redirect()->route('admin.classroom.tracks.index')
            ->with('success', 'Track created successfully.');
    }

    public function edit(Track $track)
    {
        $instructors = User::where('role', 'instructor')
            ->orWhere('role', 'admin')
            ->select('id', 'name', 'email')
            ->get();

        // Load the track with its media relationship
        $track->load('media');

        return Inertia::render('admin/classroom/TrackEditor', [
            'track' => $track,
            'instructors' => $instructors,
        ]);
    }

    public function update(Request $request, Track $track)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'slug' => 'required|string|max:255|unique:tracks,slug,' . $track->id,
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'estimated_duration' => 'nullable|integer|min:1',
            'instructor_id' => 'nullable|exists:users,id',
            'is_premium' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'is_published' => 'boolean',
            'media' => 'nullable|array',
            'media.*.id' => 'required|exists:media,id',
        ]);

        // Ensure slug is properly formatted
        $validated['slug'] = Str::slug($validated['slug']);

        // Extract media data before updating track
        $mediaData = $validated['media'] ?? [];
        unset($validated['media']);

        $track->update($validated);

        // Sync media relationships
        if (isset($mediaData)) {
            $mediaIds = collect($mediaData)->pluck('id')->toArray();
            $track->media()->sync($mediaIds);
        }

        return redirect()->route('admin.classroom.tracks.index')
            ->with('success', 'Track updated successfully.');
    }

    public function destroy(Track $track)
    {
        $track->delete();

        return redirect()->route('admin.classroom.tracks.index')
            ->with('success', 'Track deleted successfully.');
    }
}
