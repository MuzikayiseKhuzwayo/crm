<?php

namespace VentureDrake\LaravelCrm\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Services\AccountRelayService;

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

    public function initializeRelayQueue(): void
    {
        if ($this->model) {
            app(AccountRelayService::class)->initializeOrganizationBasket($this->model);
            $this->success("Account Relay Basket initialized for {$this->model->name}.");
            $this->dispatch('related-leads-updated');
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.related-leads');
    }
}
