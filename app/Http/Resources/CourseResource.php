<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'is_active' => $this->is_active,
            'estimated_duration' => $this->estimated_duration,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'modules' => ModuleResource::collection($this->whenLoaded('modules')),
            'certificate_template' => $this->whenLoaded('certificateTemplate', function () {
                return [
                    'id' => $this->certificateTemplate->id,
                    'name' => $this->certificateTemplate->name,
                    'description' => $this->certificateTemplate->description,
                ];
            }),

            // Counts
            'modules_count' => $this->when(isset($this->modules_count), $this->modules_count),
            'enrollments_count' => $this->when(isset($this->enrollments_count), $this->enrollments_count),

            // Progress data (when user is authenticated)
            'enrollment' => $this->when(isset($this->enrollment), function () {
                return $this->enrollment ? [
                    'id' => $this->enrollment->id,
                    'enrolled_at' => $this->enrollment->enrolled_at,
                    'completed_at' => $this->enrollment->completed_at,
                    'progress_percentage' => $this->enrollment->progress_percentage,
                ] : null;
            }),
            'progress' => $this->when(isset($this->progress), $this->progress),

            // URLs
            'url' => route('courses.show', $this->slug),
            'api_url' => route('api.courses.show', $this->slug),
        ];
    }
}
