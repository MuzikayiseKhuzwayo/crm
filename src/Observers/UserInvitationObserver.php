<?php

namespace VentureDrake\LaravelCrm\Observers;

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\UserInvitation;

class UserInvitationObserver
{
    public function creating(UserInvitation $userInvitation)
    {
        if (! $userInvitation->external_id) {
            $userInvitation->external_id = Uuid::uuid4()->toString();
        }

        if (! $userInvitation->code) {
            $userInvitation->code = Str::random(64);
        }
    }
}
