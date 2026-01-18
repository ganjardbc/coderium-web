<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
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
            'order_index' => $this->order_index,
            'estimated_duration' => $this->estimated_duration,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'level' => new LevelResource($this->whenLoaded('level')),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'assessments' => AssessmentResource::collection($this->whenLoaded('assessments')),
            'assignments' => AssignmentResource::collection($this->whenLoaded('assignments')),
            'media' => MediaResource::collection($this->whenLoaded('media')),

            // Computed attributes
            'lessons_count' => $this->whenCounted('lessons'),
            'assessments_count' => $this->whenCounted('assessments'),
            'assignments_count' => $this->whenCounted('assignments'),
        ];
    }
}
