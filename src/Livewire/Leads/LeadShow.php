<?php

namespace VentureDrake\LaravelCrm\Livewire\Leads;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Lead;

class LeadShow extends Component
{
    use AuthorizesRequests, Toast;

    public $lead;

    public $email;

    public $phone;

    public $address;

    public function mount(Lead $lead)
    {
        $this->lead = $lead;
        $this->email = $lead->getPrimaryEmail();
        $this->phone = $lead->getPrimaryPhone();
        $this->address = $lead->getPrimaryAddress();
    }

    public function delete($id)
    {
        if ($lead = Lead::find($id)) {
            $this->authorize('delete', $lead);

            $lead->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.lead_deleted')), redirectTo: route('laravel-crm.leads.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.leads.lead-show');
    }
}
