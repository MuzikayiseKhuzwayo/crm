<?php

namespace VentureDrake\LaravelCrm\Services\BusinessDevelopment;

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Exceptions\HandoffGateIncompleteException;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\HandoffGate;

class HandoffGateService
{
    /**
     * Standard mandatory clearance gates before deal closing.
     */
    public const REQUIRED_GATES = [
        'product_capability',
        'operational_capacity',
        'financial_margin',
    ];

    /**
     * Initialize default handoff gates for a deal if they don't already exist.
     */
    public function initializeDefaultGates(Deal $deal): void
    {
        foreach (self::REQUIRED_GATES as $gateType) {
            HandoffGate::firstOrCreate(
                [
                    'deal_id' => $deal->id,
                    'gate_type' => $gateType,
                ],
                [
                    'external_id' => (string) Str::uuid(),
                    'team_id' => $deal->team_id,
                    'status' => 'pending',
                ]
            );
        }
    }

    /**
     * Approve an operational gate.
     */
    public function approveGate(Deal $deal, string $gateType, ?int $userId = null, ?array $metadata = null): HandoffGate
    {
        $gate = HandoffGate::firstOrNew([
            'deal_id' => $deal->id,
            'gate_type' => $gateType,
        ]);

        if (! $gate->exists) {
            $gate->external_id = (string) Str::uuid();
            $gate->team_id = $deal->team_id;
        }

        $gate->status = 'approved';
        $gate->cleared_by_user_id = $userId ?? auth()->id();
        $gate->cleared_at = now();
        $gate->rejection_reason = null;

        if ($metadata) {
            $gate->metadata = array_merge($gate->metadata ?? [], $metadata);
        }

        $gate->save();

        return $gate;
    }

    /**
     * Reject an operational gate.
     */
    public function rejectGate(Deal $deal, string $gateType, ?int $userId = null, string $reason = ''): HandoffGate
    {
        $gate = HandoffGate::firstOrNew([
            'deal_id' => $deal->id,
            'gate_type' => $gateType,
        ]);

        if (! $gate->exists) {
            $gate->external_id = (string) Str::uuid();
            $gate->team_id = $deal->team_id;
        }

        $gate->status = 'rejected';
        $gate->cleared_by_user_id = $userId ?? auth()->id();
        $gate->cleared_at = now();
        $gate->rejection_reason = $reason;
        $gate->save();

        return $gate;
    }

    /**
     * Waive an operational gate with documented audit rationale.
     */
    public function waiveGate(Deal $deal, string $gateType, ?int $userId = null, string $reason = ''): HandoffGate
    {
        $gate = HandoffGate::firstOrNew([
            'deal_id' => $deal->id,
            'gate_type' => $gateType,
        ]);

        if (! $gate->exists) {
            $gate->external_id = (string) Str::uuid();
            $gate->team_id = $deal->team_id;
        }

        $gate->status = 'waived';
        $gate->cleared_by_user_id = $userId ?? auth()->id();
        $gate->cleared_at = now();
        $gate->rejection_reason = $reason;
        $gate->save();

        return $gate;
    }

    /**
     * Check whether deal has satisfied all required handoff gates.
     */
    public function canAdvanceToWon(Deal $deal): bool
    {
        return empty($this->getPendingGates($deal));
    }

    /**
     * Assert deal can advance to won, or throw exception.
     *
     * @throws HandoffGateIncompleteException
     */
    public function assertCanAdvanceToWon(Deal $deal): void
    {
        $pending = $this->getPendingGates($deal);

        if (! empty($pending)) {
            throw new HandoffGateIncompleteException($pending);
        }
    }

    /**
     * Get list of incomplete required gates.
     */
    public function getPendingGates(Deal $deal): array
    {
        $gates = $deal->handoffGates()->whereIn('gate_type', self::REQUIRED_GATES)->get();
        $clearedGateTypes = $gates->filter(fn ($g) => $g->isCleared())->pluck('gate_type')->all();

        $pending = [];
        foreach (self::REQUIRED_GATES as $requiredGate) {
            if (! in_array($requiredGate, $clearedGateTypes)) {
                $pending[] = $requiredGate;
            }
        }

        return $pending;
    }
}
