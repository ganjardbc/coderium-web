<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'file_name',
        'mime_type',
        'path',
        'disk',
        'collection_name',
        'size',
        'custom_properties',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'custom_properties' => 'array',
        'size' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['url', 'type'];

    /**
     * Get the user that owns the media.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all mediables for this media.
     */
    public function mediables(): HasMany
    {
        return $this->hasMany(Mediable::class);
    }

    /**
     * Get the full URL to the media file.
     */
    public function getUrlAttribute(): string
    {
        // For S3/R2, Storage::url() returns the full URL
        // For local, it returns /storage/path
        $url = Storage::disk($this->disk)->url($this->path);

        // If it's a relative path (local storage), prepend APP_URL
        if (!str_starts_with($url, 'http')) {
            return config('app.url') . $url;
        }

        return $url;
    }

    /**
     * Get the media type (image or video).
     */
    public function getTypeAttribute(): string
    {
        if (str_starts_with($this->mime_type, 'image/')) {
            return 'image';
        }

        if (str_starts_with($this->mime_type, 'video/')) {
            return 'video';
        }

        return 'file';
    }

    /**
     * Check if the media is an image.
     */
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    /**
     * Check if the media is a video.
     */
    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /**
     * Delete the media file from storage.
     */
    public function deleteFile(): bool
    {
        return Storage::disk($this->disk)->delete($this->path);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($media) {
            $media->deleteFile();
        });
    }
}
