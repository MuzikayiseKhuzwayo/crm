<?php

namespace VentureDrake\LaravelCrm\Livewire\BusinessDevelopment;

use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Services\BusinessDevelopment\DeriskingPlaybookService;

class DeriskingPlaybookWidget extends Component
{
    use Toast;

    public Deal $deal;

    public ?string $problemStatement = '';

    public ?string $pitfallsIdentified = '';

    public ?string $uniqueInsight = '';

    public ?string $executionPlan = '';

    public bool $isSaved = false;

    public function mount(Deal $deal)
    {
        $this->deal = $deal;
        $derisking = $deal->derisking;

        if ($derisking) {
            $this->problemStatement = $derisking->problem_statement;
            $this->pitfallsIdentified = $derisking->pitfalls_identified;
            $this->uniqueInsight = $derisking->unique_insight;
            $this->executionPlan = $derisking->execution_plan;
        }
    }

    public function save(DeriskingPlaybookService $service)
    {
        $service->updateNarrative(
            $this->deal,
            $this->problemStatement,
            $this->pitfallsIdentified,
            $this->uniqueInsight,
            $this->executionPlan
        );

        $this->isSaved = true;
        $this->success('Derisking Playbook narrative updated.');
        $this->dispatch('refreshDeal');
    }

    public function render()
    {
        return view('laravel-crm::livewire.business-development.derisking-playbook-widget');
    }
}
