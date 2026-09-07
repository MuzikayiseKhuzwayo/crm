<?php

namespace VentureDrake\LaravelCrm\Livewire\BusinessDevelopment;

use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Services\BusinessDevelopment\HandoffGateService;

class HandoffGateModal extends Component
{
    use Toast;

    public Deal $deal;

    public string $selectedGate = 'product_capability';

    public string $action = 'approve';

    public string $reason = '';

    public bool $showModal = false;

    protected $listeners = ['openHandoffModal' => 'open'];

    public function mount(Deal $deal)
    {
        $this->deal = $deal;
    }

    public function open(string $gateType = 'product_capability')
    {
        $this->selectedGate = $gateType;
        $this->action = 'approve';
        $this->reason = '';
        $this->showModal = true;
    }

    public function submitGate(HandoffGateService $gateService)
    {
        if ($this->action === 'approve') {
            $gateService->approveGate($this->deal, $this->selectedGate, auth()->id());
            $this->success("Handoff gate [{$this->selectedGate}] approved.");
        } elseif ($this->action === 'reject') {
            $gateService->rejectGate($this->deal, $this->selectedGate, auth()->id(), $this->reason);
            $this->warning("Handoff gate [{$this->selectedGate}] marked as rejected.");
        } elseif ($this->action === 'waive') {
            $gateService->waiveGate($this->deal, $this->selectedGate, auth()->id(), $this->reason);
            $this->info("Handoff gate [{$this->selectedGate}] waived with audit note.");
        }

        $this->showModal = false;
        $this->dispatch('refreshDeal');
    }

    public function render()
    {
        $gates = $this->deal->handoffGates()->get();

        return view('laravel-crm::livewire.business-development.handoff-gate-modal', [
            'gates' => $gates,
        ]);
    }
}
