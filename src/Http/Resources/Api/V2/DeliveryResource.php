<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Resources\Json\JsonResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\UserBriefResource;

class DeliveryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'delivery_id' => $this->delivery_id,
            'reference' => $this->reference,
            'delivery_expected' => $this->delivery_expected?->toIso8601String(),
            'delivered_on' => $this->delivered_on?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'owner' => $this->whenLoaded('ownerUser', fn () => $this->ownerUser ? new UserBriefResource($this->ownerUser) : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? new OrderResource($this->order) : null),
        ];
    }
}
