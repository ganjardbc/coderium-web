<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentSubmissionResource extends JsonResource
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
            'submission_text' => $this->submission_text,
            'repository_url' => $this->repository_url,
            'submitted_at' => $this->submitted_at,
            'grade' => $this->grade,
            'feedback' => $this->feedback,
            'graded_at' => $this->graded_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'assignment' => new AssignmentResource($this->whenLoaded('assignment')),
            'graded_by' => new UserResource($this->whenLoaded('gradedBy')),

            // Computed attributes
            'is_graded' => !is_null($this->grade),
            'is_late' => $this->submitted_at && $this->assignment && $this->assignment->due_date && $this->submitted_at > $this->assignment->due_date,
        ];
    }
}
