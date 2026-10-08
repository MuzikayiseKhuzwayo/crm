<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Resources\Json\JsonResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\UserBriefResource;

class MonitorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'name' => $this->name,
            'url' => $this->url,
            'host' => $this->host,
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'uptime_enabled' => (bool) $this->uptime_enabled,
            'ssl_enabled' => (bool) $this->ssl_enabled,
            'last_response_time' => $this->last_response_time,
            'last_status_code' => $this->last_status_code,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'down_since_at' => $this->down_since_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'owner' => $this->whenLoaded('ownerUser', fn () => $this->ownerUser ? new UserBriefResource($this->ownerUser) : null),
        ];
    }
}
