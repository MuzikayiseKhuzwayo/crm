<?php

namespace VentureDrake\LaravelCrm\Observers;

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;

class LinkedinCampaignRecipientObserver
{
    public function creating(LinkedinCampaignRecipient $recipient): void
    {
        if (! $recipient->external_id) {
            $recipient->external_id = Uuid::uuid4()->toString();
        }

        if (! $recipient->normalized_linkedin_slug && $recipient->linkedin_url) {
            $recipient->normalized_linkedin_slug = LinkedinCampaignRecipient::normalizeLinkedinUrl($recipient->linkedin_url);
        }
    }

    public function updating(LinkedinCampaignRecipient $recipient): void
    {
        if ($recipient->isDirty('linkedin_url') && ! $recipient->isDirty('normalized_linkedin_slug') && $recipient->linkedin_url) {
            $recipient->normalized_linkedin_slug = LinkedinCampaignRecipient::normalizeLinkedinUrl($recipient->linkedin_url);
        }
    }
}
