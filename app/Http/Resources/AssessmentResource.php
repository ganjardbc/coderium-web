<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
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
            'passing_score' => $this->passing_score,
            'max_attempts' => $this->max_attempts,
            'time_limit' => $this->time_limit,
            'is_required' => $this->is_required,
            'assessable_type' => $this->assessable_type,
            'assessable_id' => $this->assessable_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
            'attempts' => AssessmentAttemptResource::collection($this->whenLoaded('attempts')),

            // Computed attributes
            'questions_count' => $this->whenCounted('questions'),
            'attempts_count' => $this->whenCounted('attempts'),

            // User-specific data (when available)
            'user_attempts' => $this->when(isset($this->user_attempts), $this->user_attempts),
            'best_score' => $this->when(isset($this->best_score), $this->best_score),
            'has_passed' => $this->when(isset($this->has_passed), $this->has_passed),
        ];
    }
}
