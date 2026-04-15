<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discussion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'discussable_type',
        'discussable_id',
        'title',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the owning discussable model (lesson, module, or track).
     */
    public function discussable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the posts for the discussion.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class)->orderBy('created_at');
    }

    /**
     * Get the root posts (not replies) for the discussion.
     */
    public function rootPosts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class)
            ->whereNull('parent_id')
            ->orderBy('created_at');
    }
}
