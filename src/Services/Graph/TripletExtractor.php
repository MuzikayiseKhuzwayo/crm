<?php

namespace VentureDrake\LaravelCrm\Services\Graph;

use VentureDrake\LaravelCrm\Models\Contract;
use VentureDrake\LaravelCrm\Models\Deal;

class TripletExtractor
{
    /**
     * Extract knowledge graph claims from a Deal and its commercial context.
     * Returns an array of graph triplets: [subject, predicate, object, properties]
     */
    public function extractTriplets(Deal $deal): array
    {
        $triplets = [];

        // 1. Organization -> Deal
        if ($deal->organization_id && $deal->organization) {
            $triplets[] = [
                'subject' => [
                    'type' => 'Organization',
                    'id' => $deal->organization->external_id ?? (string) $deal->organization_id,
                    'name' => $deal->organization->name,
                ],
                'predicate' => 'CONTRACTS_WITH',
                'object' => [
                    'type' => 'Deal',
                    'id' => $deal->external_id,
                    'title' => $deal->title,
                ],
                'properties' => [
                    'amount' => $deal->amount,
                    'closed_status' => $deal->closed_status,
                ],
            ];
        }

        // 2. Channel Partner -> Deal (SOURCED claim)
        if ($deal->partner_id && $deal->partner) {
            $triplets[] = [
                'subject' => [
                    'type' => 'Partner',
                    'id' => $deal->partner->external_id ?? (string) $deal->partner_id,
                    'tier' => $deal->partner->partner_tier,
                ],
                'predicate' => 'SOURCED',
                'object' => [
                    'type' => 'Deal',
                    'id' => $deal->external_id,
                    'title' => $deal->title,
                ],
                'properties' => [
                    'timestamp' => now()->toISOString(),
                ],
            ];
        }

        // 3. Person Champion -> Deal (CHAMPIONED claim)
        if ($deal->person_id && $deal->person) {
            $triplets[] = [
                'subject' => [
                    'type' => 'Person',
                    'id' => $deal->person->external_id ?? (string) $deal->person_id,
                    'name' => $deal->person->name,
                ],
                'predicate' => 'CHAMPIONS',
                'object' => [
                    'type' => 'Deal',
                    'id' => $deal->external_id,
                    'title' => $deal->title,
                ],
                'properties' => [
                    'active' => true,
                    'stability_score' => 1.0,
                ],
            ];
        }

        // 4. Contract Attributes (Co-Design & Anchor Influence)
        if ($deal->contract) {
            $contract = $deal->contract;

            if ($contract->is_design_partner) {
                $triplets[] = [
                    'subject' => [
                        'type' => 'Organization',
                        'id' => $deal->organization->external_id ?? (string) $deal->organization_id,
                        'name' => $deal->organization->name ?? 'Organization',
                    ],
                    'predicate' => 'CO_DESIGNS',
                    'object' => [
                        'type' => 'Deal',
                        'id' => $deal->external_id,
                        'title' => $deal->title,
                    ],
                    'properties' => [
                        'sla_level' => $contract->sla_commitment_level,
                        'bespoke_work_ratio' => $contract->bespoke_work_ratio,
                    ],
                ];
            }

            if ($contract->is_referenceable) {
                $triplets[] = [
                    'subject' => [
                        'type' => 'Organization',
                        'id' => $deal->organization->external_id ?? (string) $deal->organization_id,
                        'name' => $deal->organization->name ?? 'Organization',
                    ],
                    'predicate' => 'SERVES_AS_ANCHOR',
                    'object' => [
                        'type' => 'Market',
                        'id' => $deal->contract->region_code ?? 'GLOBAL',
                    ],
                    'properties' => [
                        'referenceable' => true,
                    ],
                ];
            }
        }

        return $triplets;
    }
}
