<?php

namespace VentureDrake\LaravelCrm\Livewire\BusinessDevelopment;

use Livewire\Component;
use VentureDrake\LaravelCrm\Models\Contract;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\HandoffGate;
use VentureDrake\LaravelCrm\Models\PartnerProfile;
use VentureDrake\LaravelCrm\Services\Telemetry\TelemetryIngestionService;

class CommercialIntelligenceDashboard extends Component
{
    public string $selectedQuadrant = 'market_penetration';

    public function selectQuadrant(string $quadrant)
    {
        $this->selectedQuadrant = $quadrant;
    }

    public function render(TelemetryIngestionService $telemetryService)
    {
        // 1. Core KPIs across Ansoff quadrants
        $totalDeals = Deal::count();
        $wonDeals = Deal::where('closed_status', 'won')->count();
        $winRate = $totalDeals > 0 ? round(($wonDeals / $totalDeals) * 100, 1) : 0.0;

        $activePartners = PartnerProfile::where('status', 'active')->count();
        $partnerPipeline = PartnerProfile::sum('total_sourced_pipeline_amount');

        $referenceableLogos = Contract::where('is_referenceable', true)->count();
        $designPartners = Contract::where('is_design_partner', true)->count();

        // 2. Stage Gate Health
        $pendingGates = HandoffGate::where('status', 'pending')->count();
        $approvedGates = HandoffGate::where('status', 'approved')->count();

        // 3. Telemetry summary
        $quadrantTelemetry = $telemetryService->getMetricsSummary($this->selectedQuadrant);

        return view('laravel-crm::livewire.business-development.commercial-intelligence-dashboard', [
            'totalDeals' => $totalDeals,
            'wonDeals' => $wonDeals,
            'winRate' => $winRate,
            'activePartners' => $activePartners,
            'partnerPipeline' => $partnerPipeline,
            'referenceableLogos' => $referenceableLogos,
            'designPartners' => $designPartners,
            'pendingGates' => $pendingGates,
            'approvedGates' => $approvedGates,
            'quadrantTelemetry' => $quadrantTelemetry,
        ]);
    }
}
