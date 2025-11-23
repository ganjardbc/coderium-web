<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Display a listing of media.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::query()
            ->where('user_id', $request->user()->id)
            ->with(['user']);

        // Filter by type
        if ($request->has('type')) {
            if ($request->type === 'image') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($request->type === 'video') {
                $query->where('mime_type', 'like', 'video/%');
            }
        }

        // Filter by collection
        if ($request->has('collection')) {
            $query->where('collection_name', $request->collection);
        }

        $media = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($media);
    }

    /**
     * Upload a media file.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'collection' => 'nullable|string',
            'custom_properties' => 'nullable|array',
        ]);

        $file = $request->file('file');

        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Store file
        $path = $file->storeAs('media', $filename, 'public');

        // Create media record
        $media = Media::create([
            'name' => $file->getClientOriginalName(),
            'file_name' => $filename,
            'mime_type' => $file->getMimeType(),
            'path' => $path,
            'disk' => 'public',
            'collection_name' => $request->input('collection'),
            'size' => $file->getSize(),
            'custom_properties' => $request->input('custom_properties', []),
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'media' => $media,
        ], 201);
    }

    /**
     * Upload multiple media files.
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:51200',
            'collection' => 'nullable|string',
        ]);

        $uploadedMedia = [];

        foreach ($request->file('files') as $file) {
            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // Store file
            $path = $file->storeAs('media', $filename, 'public');

            // Create media record
            $media = Media::create([
                'name' => $file->getClientOriginalName(),
                'file_name' => $filename,
                'mime_type' => $file->getMimeType(),
                'path' => $path,
                'disk' => 'public',
                'collection_name' => $request->input('collection'),
                'size' => $file->getSize(),
                'custom_properties' => [],
                'user_id' => $request->user()->id,
            ]);

            $uploadedMedia[] = $media;
        }

        return response()->json([
            'message' => 'Files uploaded successfully.',
            'media' => $uploadedMedia,
        ], 201);
    }

    /**
     * Display the specified media.
     */
    public function show(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);

        return response()->json($media);
    }

    /**
     * Update the specified media.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $media = Media::findOrFail($id);

        // Authorization check
        if ($request->user()->id !== $media->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'collection_name' => 'nullable|string',
            'custom_properties' => 'nullable|array',
        ]);

        $media->update($validated);

        return response()->json([
            'message' => 'Media updated successfully.',
            'media' => $media,
        ]);
    }

    /**
     * Remove the specified media.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $media = Media::findOrFail($id);

        // Authorization check
        if ($request->user()->id !== $media->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $media->delete();

        return response()->json([
            'message' => 'Media deleted successfully.',
        ]);
    }
}
