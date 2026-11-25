<?php

namespace VentureDrake\LaravelCrm\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;

class LinkedinCampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        if ($this->hasAnyPermission($user, ['view crm linkedin-campaigns', 'view crm leads'])) {
            return true;
        }
    }

    public function view(User $user, LinkedinCampaign $campaign)
    {
        if ($this->hasAnyPermission($user, ['view crm linkedin-campaigns', 'view crm leads'])) {
            return true;
        }
    }

    public function create(User $user)
    {
        if ($this->hasAnyPermission($user, ['create crm linkedin-campaigns', 'create crm leads'])) {
            return true;
        }
    }

    public function update(User $user, LinkedinCampaign $campaign)
    {
        if ($this->hasAnyPermission($user, ['edit crm linkedin-campaigns', 'edit crm leads'])) {
            return true;
        }
    }

    public function delete(User $user, LinkedinCampaign $campaign)
    {
        if ($this->hasAnyPermission($user, ['delete crm linkedin-campaigns', 'delete crm leads'])) {
            return true;
        }
    }

    public function restore(User $user, LinkedinCampaign $campaign)
    {
        if ($this->hasAnyPermission($user, ['delete crm linkedin-campaigns', 'delete crm leads'])) {
            return true;
        }
    }

    public function forceDelete(User $user, LinkedinCampaign $campaign)
    {
        return false;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (method_exists($user, 'checkPermissionTo')) {
                if ($user->checkPermissionTo($permission)) {
                    return true;
                }
            } elseif (method_exists($user, 'hasPermissionTo')) {
                try {
                    if ($user->hasPermissionTo($permission)) {
                        return true;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return false;
    }
}
