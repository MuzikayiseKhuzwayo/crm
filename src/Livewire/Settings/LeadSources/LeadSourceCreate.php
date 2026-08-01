<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\LeadSources;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use VentureDrake\LaravelCrm\Livewire\Settings\LeadSources\Traits\HasLeadSourceCommon;
use VentureDrake\LaravelCrm\Models\LeadSource;

class LeadSourceCreate extends Component
{
    use AuthorizesRequests;
    use HasLeadSourceCommon;

    public function save()
    {
        $this->authorize('create', LeadSource::class);

        $this->validate();

        LeadSource::create([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->success(
            ucfirst(trans('laravel-crm::lang.lead_source_created')),
            redirectTo: route('laravel-crm.lead-sources.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.lead-sources.lead-source-create');
    }
}
