<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
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
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'points' => $this->points,
            'order_index' => $this->order_index,
            'explanation' => $this->explanation,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),

            // Computed attributes
            'options_count' => $this->whenCounted('options'),
        ];
    }
}
