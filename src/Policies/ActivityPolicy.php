<?php

namespace VentureDrake\LaravelCrm\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use VentureDrake\LaravelCrm\Models\Activity;

class ActivityPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any activities.
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        if ($user->hasPermissionTo('view crm activities')) {
            return true;
        }
    }

    /**
     * Determine whether the user can view the activity.
     *
     * @param  \App\Activity  $activity
     * @return mixed
     */
    public function view(User $user, Activity $activity)
    {
        if ($user->hasPermissionTo('view crm activities')) {
            return true;
        }
    }

    /**
     * Determine whether the user can create activities.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        if ($user->hasPermissionTo('create crm activities')) {
            return true;
        }
    }

    /**
     * Determine whether the user can update the activity.
     *
     * @param  \App\Activity  $activity
     * @return mixed
     */
    public function update(User $user, Activity $activity)
    {
        if ($user->hasPermissionTo('edit crm activities')) {
            return true;
        }
    }

    /**
     * Determine whether the user can delete the activity.
     *
     * @param  \App\Activity  $activity
     * @return mixed
     */
    public function delete(User $user, Activity $activity)
    {
        if ($user->hasPermissionTo('delete crm activities')) {
            return true;
        }
    }

    /**
     * Determine whether the user can restore the activity.
     *
     * @param  \App\Activity  $activity
     * @return mixed
     */
    public function restore(User $user, Activity $activity)
    {
        if ($user->hasPermissionTo('delete crm activities')) {
            return true;
        }
    }

    /**
     * Determine whether the user can permanently delete the activity.
     *
     * @param  \App\Activity  $activity
     * @return mixed
     */
    public function forceDelete(User $user, Activity $activity)
    {
        return false;
    }
}
