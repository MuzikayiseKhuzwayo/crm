<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Resources\Json\JsonResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\UserBriefResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'start_at' => $this->start_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'owner' => $this->whenLoaded('ownerUser', fn () => $this->ownerUser ? new UserBriefResource($this->ownerUser) : null),
            'assigned' => $this->whenLoaded('assignedToUser', fn () => $this->assignedToUser ? new UserBriefResource($this->assignedToUser) : null),
        ];
    }
}
