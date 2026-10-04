<?php

namespace VentureDrake\LaravelCrm\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class RelatedLeads extends Component
{
    use AuthorizesRequests;
    use Toast;

    public $model = null;

    #[Computed, On('lead-updated'), On('related-leads-updated')]
    public function leads()
    {
        if (! $this->model) {
            return collect();
        }

        return $this->model
            ->leads()
            ->with(['person', 'pipelineStage', 'ownerUser', 'leadSource'])
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('laravel-crm::livewire.related-leads');
    }
}
