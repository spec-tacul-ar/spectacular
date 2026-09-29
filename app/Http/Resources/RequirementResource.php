<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->sqid,
            'name' => $this->name,
            'description' => $this->description,
            'blocked_reason' => $this->blocked_reason,
            'is_draft' => $this->is_draft,
            'feature_id' => $this->feature_sqid,
            'reference' => $this->reference,
            'source' => $this->source,
            'weight' => $this->weight,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'activity_at' => $this->activity_at,
            'completed_at' => $this->completed_at,

            'assignments' => AssignmentResource::collection($this->whenLoaded('assignments')),
            'unknowns' => UnknownResource::collection($this->whenLoaded('unknowns')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
