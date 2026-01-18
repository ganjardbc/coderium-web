<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
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
            'content' => $this->content,
            'order_index' => $this->order_index,
            'estimated_duration' => $this->estimated_duration,
            'is_published' => $this->is_published,
            'lesson_type' => $this->lesson_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'module' => new ModuleResource($this->whenLoaded('module')),
            'assessments' => AssessmentResource::collection($this->whenLoaded('assessments')),
            'discussions' => DiscussionResource::collection($this->whenLoaded('discussions')),
            'media' => MediaResource::collection($this->whenLoaded('media')),

            // Progress data (when available)
            'progress' => $this->when(isset($this->progress), $this->progress),
            'is_completed' => $this->when(isset($this->is_completed), $this->is_completed),
        ];
    }
}
