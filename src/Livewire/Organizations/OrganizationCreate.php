<?php

namespace VentureDrake\LaravelCrm\Livewire\Organizations;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use VentureDrake\LaravelCrm\Livewire\Organizations\Traits\HasOrganizationCommon;
use VentureDrake\LaravelCrm\Models\Organization;

class OrganizationCreate extends Component
{
    use AuthorizesRequests, HasOrganizationCommon;

    public function mount()
    {
        $this->mountCommon();

        $this->addPhone();

        $this->addEmail();

        $this->addAddress();

        $this->user_owner_id = auth()->user()->id;
    }

    public function save()
    {
        $this->authorize('create', Organization::class);

        $this->validate();

        // Create a request object to pass to services
        $request = \VentureDrake\LaravelCrm\Http\Helpers\PublicProperties\asRequest($this);

        $organization = $this->organizationService->create($request);

        $organization->labels()->sync($request->labels ?? []);

        $this->saveCustomFields($organization);

        $this->success(
            ucfirst(trans('laravel-crm::lang.organization_created')),
            redirectTo: route('laravel-crm.organizations.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.organizations.organization-create');
    }
}
