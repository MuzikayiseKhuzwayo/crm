<?php

namespace VentureDrake\LaravelCrm\Livewire\BusinessDevelopment;

use Illuminate\Support\Str;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Contract;
use VentureDrake\LaravelCrm\Models\Deal;

class ContractTelemetryCard extends Component
{
    use Toast;

    public Deal $deal;

    public string $contractType = 'direct';

    public int $termMonths = 12;

    public ?string $paymentTerms = 'NET_30';

    public string $slaLevel = 'standard';

    public bool $slaPenalty = false;

    public float $partnerRevShare = 0.0;

    public float $escalationPercent = 0.0;

    public float $minCommitment = 0.0;

    public float $bespokeWorkRatio = 0.0;

    public bool $isReferenceable = false;

    public bool $isDesignPartner = false;

    public function mount(Deal $deal)
    {
        $this->deal = $deal;
        $contract = $deal->contract;

        if ($contract) {
            $this->contractType = $contract->contract_type;
            $this->termMonths = (int) $contract->term_months;
            $this->paymentTerms = $contract->payment_terms;
            $this->slaLevel = $contract->sla_commitment_level;
            $this->slaPenalty = (bool) $contract->sla_penalty_clause;
            $this->partnerRevShare = (float) $contract->partner_rev_share_percent;
            $this->escalationPercent = (float) $contract->annual_price_escalation_percent;
            $this->minCommitment = (float) ($contract->minimum_commitment_amount / 100);
            $this->bespokeWorkRatio = (float) $contract->bespoke_work_ratio;
            $this->isReferenceable = (bool) $contract->is_referenceable;
            $this->isDesignPartner = (bool) $contract->is_design_partner;
        }
    }

    public function save()
    {
        $contract = $this->deal->contract ?? new Contract;

        if (! $contract->exists) {
            $contract->external_id = (string) Str::uuid();
            $contract->deal_id = $this->deal->id;
            $contract->organization_id = $this->deal->organization_id;
            $contract->team_id = $this->deal->team_id;
        }

        $contract->contract_type = $this->contractType;
        $contract->term_months = $this->termMonths;
        $contract->payment_terms = $this->paymentTerms;
        $contract->sla_commitment_level = $this->slaLevel;
        $contract->sla_penalty_clause = $this->slaPenalty;
        $contract->partner_rev_share_percent = $this->partnerRevShare;
        $contract->annual_price_escalation_percent = $this->escalationPercent;
        $contract->minimum_commitment_amount = (int) round($this->minCommitment * 100);
        $contract->bespoke_work_ratio = $this->bespokeWorkRatio;
        $contract->is_referenceable = $this->isReferenceable;
        $contract->is_design_partner = $this->isDesignPartner;
        $contract->save();

        $this->success('Contract telemetry updated.');
        $this->dispatch('refreshDeal');
    }

    public function render()
    {
        return view('laravel-crm::livewire.business-development.contract-telemetry-card');
    }
}
