<?php

namespace VentureDrake\LaravelCrm\Livewire\Teams;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Team;

class TeamShow extends Component
{
    use AuthorizesRequests, Toast;

    public Team $team;

    public function delete($id)
    {
        if ($team = Team::find($id)) {
            $this->authorize('delete', $team);

            $team->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.team_deleted')), redirectTo: route('laravel-crm.teams.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.teams.team-show');
    }
}
