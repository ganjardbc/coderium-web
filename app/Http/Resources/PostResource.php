<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'content' => $this->content,
            'tags' => $this->tags,
            'cover' => $this->cover,
            'type' => $this->type,
            'media' => $this->media,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at ? $this->published_at->toISOString() : null,
            'views_count' => $this->views_count,
            'likes_count' => $this->likes_count,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'user' => new UserResource($this->whenLoaded('user')),
            'playlists' => PlaylistResource::collection($this->whenLoaded('playlists')),
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
