<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Resources\Json\JsonResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\OrganizationBriefResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\PersonBriefResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\UserBriefResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'purchase_order_id' => $this->purchase_order_id,
            'reference' => $this->reference,
            'issue_date' => $this->issue_date?->toIso8601String(),
            'delivery_date' => $this->delivery_date?->toIso8601String(),
            'subtotal' => $this->subtotal !== null ? $this->subtotal / 100 : null,
            'tax' => $this->tax !== null ? $this->tax / 100 : null,
            'total' => $this->total !== null ? $this->total / 100 : null,
            'currency' => $this->currency,
            'terms' => $this->terms,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'owner' => $this->whenLoaded('ownerUser', fn () => $this->ownerUser ? new UserBriefResource($this->ownerUser) : null),
            'person' => $this->whenLoaded('person', fn () => $this->person ? new PersonBriefResource($this->person) : null),
            'organization' => $this->whenLoaded('organization', fn () => $this->organization ? new OrganizationBriefResource($this->organization) : null),
        ];
    }
}
