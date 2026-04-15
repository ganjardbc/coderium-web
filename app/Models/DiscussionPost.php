<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionPost extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'discussion_id',
        'user_id',
        'parent_id',
        'content',
        'is_instructor_response',
        'is_moderated',
        'moderation_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_instructor_response' => 'boolean',
        'is_moderated' => 'boolean',
    ];

    /**
     * Get the discussion that owns the post.
     */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    /**
     * Get the user that owns the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent post.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DiscussionPost::class, 'parent_id');
    }

    /**
     * Get the replies to this post.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * Check if this is a root post (not a reply).
     */
    public function isRootPost(): bool
    {
        return $this->parent_id === null;
    }
}
