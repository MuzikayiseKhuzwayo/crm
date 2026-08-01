<?php

namespace VentureDrake\LaravelCrm\Livewire\Organizations;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Organization;

class OrganizationShow extends Component
{
    use AuthorizesRequests, Toast;

    public Organization $organization;

    public function delete($id)
    {
        if ($organization = Organization::find($id)) {
            $this->authorize('delete', $organization);

            $organization->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.organization_deleted')), redirectTo: route('laravel-crm.organizations.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.organizations.organization-show');
    }
}
