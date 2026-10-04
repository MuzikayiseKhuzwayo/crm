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

    public function getOutreachSummaryProperty(): array
    {
        return $this->organization->outreachSummary();
    }

    public function markDisqualified(): void
    {
        $this->organization->markDisqualified();
        $this->organization->refresh();
        $this->success('Company marked as Do Not Contact / Disqualified.');
        $this->dispatch('related-leads-updated');
    }

    public function clearDisqualified(): void
    {
        $this->organization->clearDisqualified();
        $this->organization->refresh();
        $this->success('Company disqualification cleared.');
        $this->dispatch('related-leads-updated');
    }

    public function render()
    {
        return view('laravel-crm::livewire.organizations.organization-show');
    }
}
