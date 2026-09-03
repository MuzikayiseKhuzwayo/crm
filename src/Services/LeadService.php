<?php

namespace VentureDrake\LaravelCrm\Services;

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Repositories\LeadRepository;

class LeadService
{
    /**
     * @var LeadRepository
     */
    private $leadRepository;

    /**
     * LeadService constructor.
     */
    public function __construct(LeadRepository $leadRepository)
    {
        $this->leadRepository = $leadRepository;
    }

    public function create($request, $person = null, $organization = null, $client = null)
    {
        $request = is_array($request) ? (object) $request : $request;

        $lead = Lead::create([
            'external_id' => Uuid::uuid4()->toString(),
            'person_id' => $person->id ?? null,
            'organization_id' => $organization->id ?? null,
            'client_id' => $client->id ?? null,
            'title' => $request->title,
            'description' => $request->description ?? null,
            'amount' => $request->amount ?? null,
            'currency' => $request->currency ?? null,
            'lead_status_id' => 1,
            'lead_source_id' => $request->lead_source_id ?? null,
            'user_owner_id' => $request->user_owner_id ?? null,
            'linkedin' => $request->linkedin ?? null,
            'twitter' => $request->twitter ?? null,
            'website' => $request->website ?? null,
            'pipeline_id' => optional(PipelineStage::find($request->pipeline_stage_id ?? null))->pipeline?->id,
            'pipeline_stage_id' => $request->pipeline_stage_id ?? null,
        ]);

        $lead->labels()->sync($request->labels ?? []);

        return $lead;
    }

    public function update($request, Lead $lead, $person = null, $organization = null, $client = null)
    {
        $request = is_array($request) ? (object) $request : $request;

        $lead->update([
            'person_id' => $person->id ?? null,
            'organization_id' => $organization->id ?? null,
            'client_id' => $client->id ?? null,
            'title' => $request->title,
            'description' => $request->description ?? null,
            'amount' => $request->amount ?? null,
            'currency' => $request->currency ?? null,
            'lead_source_id' => $request->lead_source_id ?? null,
            'user_owner_id' => $request->user_owner_id ?? null,
            'linkedin' => $request->linkedin ?? null,
            'twitter' => $request->twitter ?? null,
            'website' => $request->website ?? null,
            'pipeline_id' => optional(PipelineStage::find($request->pipeline_stage_id ?? null))->pipeline?->id,
            'pipeline_stage_id' => $request->pipeline_stage_id ?? null,
        ]);

        $lead->labels()->sync($request->labels ?? []);

        return $lead;
    }
}
