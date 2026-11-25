<?php

namespace VentureDrake\LaravelCrm\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;

class LinkedinTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        if ($this->hasAnyPermission($user, ['view crm linkedin-templates', 'view crm linkedin-campaigns', 'view crm leads'])) {
            return true;
        }
    }

    public function view(User $user, LinkedinTemplate $template)
    {
        if ($this->hasAnyPermission($user, ['view crm linkedin-templates', 'view crm linkedin-campaigns', 'view crm leads'])) {
            return true;
        }
    }

    public function create(User $user)
    {
        if ($this->hasAnyPermission($user, ['create crm linkedin-templates', 'create crm linkedin-campaigns', 'create crm leads'])) {
            return true;
        }
    }

    public function update(User $user, LinkedinTemplate $template)
    {
        if ($this->hasAnyPermission($user, ['edit crm linkedin-templates', 'edit crm linkedin-campaigns', 'edit crm leads'])) {
            return true;
        }
    }

    public function delete(User $user, LinkedinTemplate $template)
    {
        if ($this->hasAnyPermission($user, ['delete crm linkedin-templates', 'delete crm linkedin-campaigns', 'delete crm leads'])) {
            return true;
        }
    }

    public function restore(User $user, LinkedinTemplate $template)
    {
        if ($this->hasAnyPermission($user, ['delete crm linkedin-templates', 'delete crm linkedin-campaigns', 'delete crm leads'])) {
            return true;
        }
    }

    public function forceDelete(User $user, LinkedinTemplate $template)
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
