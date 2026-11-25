<?php

namespace VentureDrake\LaravelCrm\Observers;

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;

class LinkedinCampaignObserver
{
    public function creating(LinkedinCampaign $campaign): void
    {
        if (! $campaign->external_id) {
            $campaign->external_id = Uuid::uuid4()->toString();
        }

        if (! app()->runningInConsole()) {
            $campaign->user_created_id = auth()->user()->id ?? null;
        }
    }

    public function updating(LinkedinCampaign $campaign): void
    {
        if (! app()->runningInConsole()) {
            $campaign->user_updated_id = auth()->user()->id ?? null;
        }
    }
}
