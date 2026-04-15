<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
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
            'instructions' => $this->instructions,
            'due_date' => $this->due_date,
            'max_points' => $this->max_points,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'module' => new ModuleResource($this->whenLoaded('module')),
            'submissions' => AssignmentSubmissionResource::collection($this->whenLoaded('submissions')),

            // Computed attributes
            'submissions_count' => $this->whenCounted('submissions'),

            // User-specific data (when available)
            'user_submission' => $this->when(isset($this->user_submission), $this->user_submission),
        ];
    }
}
