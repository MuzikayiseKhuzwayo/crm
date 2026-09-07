<?php

namespace VentureDrake\LaravelCrm\Services\BusinessDevelopment;

use VentureDrake\LaravelCrm\Models\Contract;
use VentureDrake\LaravelCrm\Models\Deal;

class ContractTelemetryService
{
    /**
     * Calculate Sales Qualified Opportunity (SQO) Velocity in hours.
     * Time elapsed between lead creation/qualification and deal proposal creation.
     */
    public function calculateSqoVelocityHours(Deal $deal): ?float
    {
        if (! $deal->lead) {
            return null;
        }

        $leadStart = $deal->lead->created_at;
        $dealCreated = $deal->created_at;

        if (! $leadStart || ! $dealCreated) {
            return null;
        }

        return round($dealCreated->diffInMinutes($leadStart) / 60, 2);
    }

    /**
     * Calculate Contract-to-Kickoff Latency in calendar days.
     */
    public function calculateContractToKickoffDays(Contract $contract): ?int
    {
        if (! $contract->signed_at || ! $contract->kickoff_at) {
            return null;
        }

        return $contract->signed_at->diffInDays($contract->kickoff_at);
    }

    /**
     * Calculate Net Realized Margin via Channel after rev-share deduction.
     * Returns net margin amount in integer cents.
     */
    public function calculateNetRealizedMarginAmount(Contract $contract, int $grossAmountCents): int
    {
        $revSharePercent = (float) $contract->partner_rev_share_percent;
        if ($revSharePercent <= 0) {
            return $grossAmountCents;
        }

        $revShareDeduction = (int) round($grossAmountCents * ($revSharePercent / 100));

        return max(0, $grossAmountCents - $revShareDeduction);
    }

    /**
     * Calculate Discount Compression percentage concession.
     */
    public function calculateDiscountCompressionPercent(int $standardListPriceCents, int $discountedPriceCents): float
    {
        if ($standardListPriceCents <= 0 || $discountedPriceCents >= $standardListPriceCents) {
            return 0.00;
        }

        $discount = $standardListPriceCents - $discountedPriceCents;

        return round(($discount / $standardListPriceCents) * 100, 2);
    }
}
