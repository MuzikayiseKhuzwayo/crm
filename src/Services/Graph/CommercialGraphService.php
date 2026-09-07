<?php

namespace VentureDrake\LaravelCrm\Services\Graph;

use Illuminate\Support\Facades\Cache;
use VentureDrake\LaravelCrm\Models\Deal;

class CommercialGraphService
{
    protected TripletExtractor $extractor;

    public function __construct(TripletExtractor $extractor)
    {
        $this->extractor = $extractor;
    }

    /**
     * Sync deal relationships into graph claims.
     *
     * @return array Array of extracted and synchronized triplets
     */
    public function syncDealToGraph(Deal $deal): array
    {
        $triplets = $this->extractor->extractTriplets($deal);

        // Store active claims in cache/graph store
        $cacheKey = 'commercial_graph_claims:'.$deal->external_id;
        Cache::put($cacheKey, $triplets, now()->addDays(7));

        return $triplets;
    }

    /**
     * Retrieve graph claims for a given deal external ID.
     */
    public function getClaimsForDeal(string $dealExternalId): array
    {
        return Cache::get('commercial_graph_claims:'.$dealExternalId, []);
    }

    /**
     * Traverse influence path: Find if a deal was unlocked by an anchor client.
     */
    public function findAnchorInfluence(string $dealExternalId): array
    {
        $claims = $this->getClaimsForDeal($dealExternalId);

        return array_filter($claims, fn ($c) => in_array($c['predicate'], ['SERVES_AS_ANCHOR', 'UNLOCKED', 'SOURCED']));
    }
}
