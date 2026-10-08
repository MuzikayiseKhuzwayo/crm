<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Resources\Json\JsonResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\UserBriefResource;

class FeatureResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'feature_id' => $this->feature_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status ? [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'color' => $this->status->color,
            ] : null,
            'is_public' => (bool) $this->is_public,
            'votes_count' => (int) $this->votes_count,
            'comments_count' => (int) $this->comments_count,
            'views_count' => (int) $this->views_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'owner' => $this->whenLoaded('ownerUser', fn () => $this->ownerUser ? new UserBriefResource($this->ownerUser) : null),
        ];
    }
}
