<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscussionResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'discussable_type' => $this->discussable_type,
            'discussable_id' => $this->discussable_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'posts' => DiscussionPostResource::collection($this->whenLoaded('posts')),

            // Computed attributes
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
