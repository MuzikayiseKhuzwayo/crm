<?php

namespace VentureDrake\LaravelCrm\Observers;

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;

class LinkedinTemplateObserver
{
    public function creating(LinkedinTemplate $template): void
    {
        if (! $template->external_id) {
            $template->external_id = Uuid::uuid4()->toString();
        }

        if (! app()->runningInConsole()) {
            $template->user_created_id = auth()->user()->id ?? null;
        }
    }

    public function updating(LinkedinTemplate $template): void
    {
        if (! app()->runningInConsole()) {
            $template->user_updated_id = auth()->user()->id ?? null;
        }
    }
}
