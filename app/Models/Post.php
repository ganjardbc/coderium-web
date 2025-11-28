<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'content',
        'tags',
        'cover',
        'type',
        'media',
        'user_id',
        'is_published',
        'published_at',
        'views_count',
        'likes_count',
        'meta_description',
        'meta_keywords',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'media' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'views_count' => 'integer',
        'likes_count' => 'integer',
    ];

    /**
     * Get the user that owns the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The playlists that the post belongs to.
     */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_post')
            ->withPivot('order', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the views for the post.
     */
    public function views(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    /**
     * Get the likes for the post.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    /**
     * Get all media for the post.
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot(['tag', 'order'])
            ->withTimestamps()
            ->orderBy('mediables.order');
    }

    /**
     * Get carousel images for the post.
     */
    public function carouselImages(): MorphToMany
    {
        return $this->media()->wherePivot('tag', 'carousel');
    }

    /**
     * Get video media for the post.
     */
    public function videoMedia(): MorphToMany
    {
        return $this->media()->wherePivot('tag', 'video');
    }

    /**
     * Get stack gallery images for the post.
     */
    public function stackGalleryImages(): MorphToMany
    {
        return $this->media()->wherePivot('tag', 'stack_gallery');
    }

    /**
     * Increment the views count.
     */
    public function incrementViews(string $ipAddress, ?string $userAgent = null, ?string $referer = null): void
    {
        $this->increment('views_count');

        $this->views()->create([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'viewed_at' => now(),
        ]);
    }
}
