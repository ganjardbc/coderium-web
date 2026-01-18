<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackResource extends JsonResource
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
            'slug' => $this->slug,
            'is_premium' => $this->is_premium,
            'price' => $this->when($this->is_premium, $this->price),
            'is_published' => $this->is_published,
            'difficulty_level' => $this->difficulty_level,
            'estimated_duration' => $this->estimated_duration,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'instructor' => new UserResource($this->whenLoaded('instructor')),
            'levels' => LevelResource::collection($this->whenLoaded('levels')),
            'media' => MediaResource::collection($this->whenLoaded('media')),

            // Computed attributes
            'is_free' => $this->isFree(),
            'levels_count' => $this->whenCounted('levels'),
            'enrollments_count' => $this->whenCounted('enrollments'),

            // Progress data (when available)
            'enrollment' => $this->when(isset($this->enrollment), $this->enrollment),
            'progress' => $this->when(isset($this->progress), $this->progress),
        ];
    }
}
