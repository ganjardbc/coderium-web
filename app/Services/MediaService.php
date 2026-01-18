<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;

class MediaService
{
    /**
     * Classroom-specific file type constraints.
     */
    private const CLASSROOM_CONSTRAINTS = [
        'video' => [
            'max_size' => 104857600, // 100MB
            'max_duration' => 600, // 10 minutes in seconds
            'allowed_types' => ['video/mp4', 'video/webm', 'video/ogg'],
        ],
        'image' => [
            'max_size' => 10485760, // 10MB
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            'max_width' => 2048,
            'max_height' => 2048,
        ],
        'document' => [
            'max_size' => 52428800, // 50MB
            'allowed_types' => ['application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ],
    ];

    /**
     * Upload media for classroom content.
     *
     * @param UploadedFile $file
     * @param User $user
     * @param string $collection
     * @param array $customProperties
     * @return Media
     * @throws ValidationException
     */
    public function uploadClassroomMedia(UploadedFile $file, User $user, string $collection = 'classroom', array $customProperties = []): Media
    {
        // Validate file for classroom use
        $this->validateClassroomFile($file);

        // Process file based on type
        $processedFile = $this->processFileForClassroom($file);

        // Upload to storage
        $media = $this->storeFile($processedFile, $user, $collection, $customProperties);

        // Add classroom-specific metadata
        $this->addClassroomMetadata($media, $file);

        return $media;
    }

    /**
     * Attach media to classroom content.
     *
     * @param Media $media
     * @param mixed $content
     * @param string $tag
     * @param int $order
     * @return bool
     */
    public function attachToClassroomContent(Media $media, $content, string $tag = 'default', int $order = 0): bool
    {
        // Validate content supports media attachment
        if (!method_exists($content, 'media')) {
            throw new \InvalidArgumentException('Content does not support media attachment');
        }

        // Attach with pivot data
        $content->media()->attach($media->id, [
            'tag' => $tag,
            'order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Detach media from classroom content.
     *
     * @param Media $media
     * @param mixed $content
     * @return bool
     */
    public function detachFromClassroomContent(Media $media, $content): bool
    {
        if (!method_exists($content, 'media')) {
            throw new \InvalidArgumentException('Content does not support media attachment');
        }

        $content->media()->detach($media->id);
        return true;
    }

    /**
     * Get media for classroom content with specific tag.
     *
     * @param mixed $content
     * @param string|null $tag
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getClassroomMedia($content, ?string $tag = null)
    {
        if (!method_exists($content, 'media')) {
            throw new \InvalidArgumentException('Content does not support media retrieval');
        }

        $query = $content->media();

        if ($tag) {
            $query->wherePivot('tag', $tag);
        }

        return $query->orderBy('mediables.order')->get();
    }

    /**
     * Compress image for classroom use.
     *
     * @param UploadedFile $file
     * @return UploadedFile
     */
    public function compressImage(UploadedFile $file): UploadedFile
    {
        // Skip compression for SVG files
        if ($file->getMimeType() === 'image/svg+xml') {
            return $file;
        }

        $constraints = self::CLASSROOM_CONSTRAINTS['image'];

        // Create image instance
        $image = Image::make($file->getRealPath());

        // Get original dimensions
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Calculate new dimensions if needed
        if ($originalWidth > $constraints['max_width'] || $originalHeight > $constraints['max_height']) {
            $image->resize($constraints['max_width'], $constraints['max_height'], function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Optimize quality based on file size
        $quality = $this->calculateOptimalQuality($file->getSize());

        // Save compressed image to temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'classroom_image_');
        $image->save($tempPath, $quality);

        // Create new UploadedFile instance
        return new UploadedFile(
            $tempPath,
            $file->getClientOriginalName(),
            $file->getMimeType(),
            null,
            true // Mark as test file to avoid validation issues
        );
    }

    /**
     * Validate video duration and properties.
     *
     * @param UploadedFile $file
     * @return array
     * @throws ValidationException
     */
    public function validateVideoProperties(UploadedFile $file): array
    {
        $constraints = self::CLASSROOM_CONSTRAINTS['video'];

        try {
            // Use FFProbe to get video information
            $ffprobe = FFProbe::create();
            $duration = $ffprobe->format($file->getRealPath())->get('duration');
            $videoStream = $ffprobe->streams($file->getRealPath())->videos()->first();

            $properties = [
                'duration' => (float) $duration,
                'width' => $videoStream->get('width'),
                'height' => $videoStream->get('height'),
                'bitrate' => $ffprobe->format($file->getRealPath())->get('bit_rate'),
            ];

            // Validate duration
            if ($duration > $constraints['max_duration']) {
                throw ValidationException::withMessages([
                    'file' => "Video duration ({$duration}s) exceeds maximum allowed duration ({$constraints['max_duration']}s).",
                ]);
            }

            return $properties;

        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'file' => 'Unable to process video file. Please ensure it is a valid video format.',
            ]);
        }
    }

    /**
     * Validate file for classroom use.
     *
     * @param UploadedFile $file
     * @throws ValidationException
     */
    private function validateClassroomFile(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        // Determine file type category
        $category = $this->getFileCategory($mimeType);

        if (!$category) {
            throw ValidationException::withMessages([
                'file' => 'File type not supported for classroom content.',
            ]);
        }

        $constraints = self::CLASSROOM_CONSTRAINTS[$category];

        // Validate file size
        if ($fileSize > $constraints['max_size']) {
            $maxSizeMB = round($constraints['max_size'] / 1048576, 1);
            throw ValidationException::withMessages([
                'file' => "File size exceeds maximum allowed size of {$maxSizeMB}MB.",
            ]);
        }

        // Validate MIME type
        if (!in_array($mimeType, $constraints['allowed_types'])) {
            throw ValidationException::withMessages([
                'file' => 'File type not allowed for classroom content.',
            ]);
        }

        // Additional validation for specific file types
        if ($category === 'video') {
            $this->validateVideoProperties($file);
        } elseif ($category === 'image') {
            $this->validateImageProperties($file);
        }
    }

    /**
     * Validate image properties.
     *
     * @param UploadedFile $file
     * @throws ValidationException
     */
    private function validateImageProperties(UploadedFile $file): void
    {
        // Skip validation for SVG files
        if ($file->getMimeType() === 'image/svg+xml') {
            return;
        }

        $constraints = self::CLASSROOM_CONSTRAINTS['image'];

        try {
            $imageInfo = getimagesize($file->getRealPath());

            if (!$imageInfo) {
                throw ValidationException::withMessages([
                    'file' => 'Invalid image file.',
                ]);
            }

            [$width, $height] = $imageInfo;

            // Check dimensions (allow larger images as they will be compressed)
            if ($width > $constraints['max_width'] * 2 || $height > $constraints['max_height'] * 2) {
                throw ValidationException::withMessages([
                    'file' => "Image dimensions too large. Maximum recommended: {$constraints['max_width']}x{$constraints['max_height']}px.",
                ]);
            }

        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'file' => 'Unable to process image file.',
            ]);
        }
    }

    /**
     * Process file for classroom use.
     *
     * @param UploadedFile $file
     * @return UploadedFile
     */
    private function processFileForClassroom(UploadedFile $file): UploadedFile
    {
        $category = $this->getFileCategory($file->getMimeType());

        switch ($category) {
            case 'image':
                return $this->compressImage($file);
            case 'video':
                // Video processing could be added here (transcoding, etc.)
                return $file;
            case 'document':
                return $file;
            default:
                return $file;
        }
    }

    /**
     * Store file to configured storage.
     *
     * @param UploadedFile $file
     * @param User $user
     * @param string $collection
     * @param array $customProperties
     * @return Media
     */
    private function storeFile(UploadedFile $file, User $user, string $collection, array $customProperties): Media
    {
        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Get configured disk
        $disk = config('filesystems.default');

        // Store file
        try {
            if ($disk === 's3') {
                $path = Storage::disk('s3')->putFileAs('classroom', $file, $filename);
            } else {
                $path = $file->storeAs('classroom', $filename, $disk);
            }

            if (!$path) {
                throw new \Exception('File storage failed');
            }

        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'file' => 'Failed to store file: ' . $e->getMessage(),
            ]);
        }

        // Create media record
        return Media::create([
            'name' => $file->getClientOriginalName(),
            'file_name' => $filename,
            'mime_type' => $file->getMimeType(),
            'path' => $path,
            'disk' => $disk,
            'collection_name' => $collection,
            'size' => $file->getSize(),
            'custom_properties' => $customProperties,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Add classroom-specific metadata to media.
     *
     * @param Media $media
     * @param UploadedFile $file
     * @return void
     */
    private function addClassroomMetadata(Media $media, UploadedFile $file): void
    {
        $category = $this->getFileCategory($file->getMimeType());
        $metadata = ['category' => $category];

        switch ($category) {
            case 'video':
                try {
                    $videoProperties = $this->validateVideoProperties($file);
                    $metadata = array_merge($metadata, $videoProperties);
                } catch (\Exception $e) {
                    // Metadata extraction failed, continue without video metadata
                }
                break;

            case 'image':
                if ($file->getMimeType() !== 'image/svg+xml') {
                    $imageInfo = getimagesize($file->getRealPath());
                    if ($imageInfo) {
                        $metadata['width'] = $imageInfo[0];
                        $metadata['height'] = $imageInfo[1];
                    }
                }
                break;
        }

        // Update custom properties with metadata
        $customProperties = $media->custom_properties ?? [];
        $customProperties['classroom_metadata'] = $metadata;

        $media->update(['custom_properties' => $customProperties]);
    }

    /**
     * Get file category based on MIME type.
     *
     * @param string $mimeType
     * @return string|null
     */
    private function getFileCategory(string $mimeType): ?string
    {
        foreach (self::CLASSROOM_CONSTRAINTS as $category => $constraints) {
            if (in_array($mimeType, $constraints['allowed_types'])) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Calculate optimal image quality based on file size.
     *
     * @param int $fileSize
     * @return int
     */
    private function calculateOptimalQuality(int $fileSize): int
    {
        // Base quality on file size
        if ($fileSize > 5242880) { // > 5MB
            return 70;
        } elseif ($fileSize > 2097152) { // > 2MB
            return 80;
        } else {
            return 90;
        }
    }
}
