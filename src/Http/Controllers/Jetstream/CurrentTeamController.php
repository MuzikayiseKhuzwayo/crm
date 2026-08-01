<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Jetstream;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Laravel\Jetstream\Jetstream;
use Spatie\Permission\PermissionRegistrar;
use VentureDrake\LaravelCrm\Models\Team as CrmTeam;

class CurrentTeamController extends Controller
{
    /**
     * Update the authenticated user's current team.
     *
     * @return RedirectResponse
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $team = $this->resolveTeamModel()->findOrFail($request->team_id);

        if (method_exists($user, 'switchTeam')) {
            if (! $user->switchTeam($team)) {
                abort(403);
            }
        } elseif (Schema::hasColumn($user->getTable(), 'current_team_id')) {
            $user->forceFill(['current_team_id' => $team->id])->save();
        } else {
            abort(403);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $redirect = config('fortify.home') ?? route('laravel-crm.dashboard');

        return redirect($redirect, 303);
    }

    protected function resolveTeamModel()
    {
        if (class_exists(Jetstream::class)) {
            return Jetstream::newTeamModel();
        }

        return new CrmTeam;
    }
}
