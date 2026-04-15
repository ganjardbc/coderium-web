<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentAttemptResource extends JsonResource
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
            'score' => $this->score,
            'max_score' => $this->max_score,
            'passed' => $this->passed,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'time_taken' => $this->time_taken,
            'attempt_number' => $this->attempt_number,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'assessment' => new AssessmentResource($this->whenLoaded('assessment')),
            'answers' => AttemptAnswerResource::collection($this->whenLoaded('answers')),

            // Computed attributes
            'percentage' => $this->max_score > 0 ? round(($this->score / $this->max_score) * 100, 2) : 0,
        ];
    }
}
