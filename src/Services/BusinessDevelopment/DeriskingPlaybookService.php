<?php

namespace VentureDrake\LaravelCrm\Services\BusinessDevelopment;

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\DealDerisking;

class DeriskingPlaybookService
{
    /**
     * Get or initialize the derisking playbook for a deal.
     */
    public function getOrInitialize(Deal $deal): DealDerisking
    {
        return DealDerisking::firstOrCreate(
            ['deal_id' => $deal->id],
            [
                'external_id' => (string) Str::uuid(),
                'team_id' => $deal->team_id,
            ]
        );
    }

    /**
     * Update the 4-part narrative arc for an enterprise deal.
     */
    public function updateNarrative(
        Deal $deal,
        ?string $problemStatement,
        ?string $pitfallsIdentified,
        ?string $uniqueInsight,
        ?string $executionPlan,
        ?int $competitorId = null
    ): DealDerisking {
        $derisking = $this->getOrInitialize($deal);

        $derisking->update([
            'problem_statement' => $problemStatement,
            'pitfalls_identified' => $pitfallsIdentified,
            'unique_insight' => $uniqueInsight,
            'execution_plan' => $executionPlan,
            'competitor_id' => $competitorId,
        ]);

        return $derisking;
    }

    /**
     * Record LOI signed date for diversification deals.
     */
    public function recordLoiSigned(Deal $deal): DealDerisking
    {
        $derisking = $this->getOrInitialize($deal);
        $derisking->update([
            'loi_signed_at' => now(),
        ]);

        return $derisking;
    }

    /**
     * Record conversion from pilot / non-binding LOI to paid enterprise contract.
     */
    public function recordPilotConverted(Deal $deal): DealDerisking
    {
        $derisking = $this->getOrInitialize($deal);
        $derisking->update([
            'pilot_converted_at' => now(),
            'commercial_thesis_validated' => true,
        ]);

        return $derisking;
    }

    /**
     * Record early hypothesis invalidation (killing non-viable ventures early).
     */
    public function recordHypothesisInvalidated(Deal $deal, string $rationale): DealDerisking
    {
        $derisking = $this->getOrInitialize($deal);
        $derisking->update([
            'commercial_thesis_validated' => false,
            'pitfalls_identified' => trim(($derisking->pitfalls_identified ?? '')."\n[Invalidation Rationale]: ".$rationale),
        ]);

        return $derisking;
    }
}
