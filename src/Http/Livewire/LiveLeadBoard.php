<?php

namespace VentureDrake\LaravelCrm\Http\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use VentureDrake\LaravelCrm\Http\Livewire\KanbanBoard\KanbanBoard;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;

class LiveLeadBoard extends KanbanBoard
{
    use AuthorizesRequests;

    public $model = 'lead';

    public $leads;

    public function stages(): Collection
    {
        if ($pipeline = Pipeline::where('model', get_class(new Lead))->first()) {
            return $pipeline->pipelineStages()
                ->orderBy('order')
                ->orderBy('id')
                ->get();
        }
    }

    public function onStageSorted($orderedIds)
    {
        // The id list arrives straight from the browser, so every record it names is
        // authorized in its own right before it is touched. Mirrors LeadBoard.
        foreach ($orderedIds as $orderNumber => $leadId) {
            if (! $record = Lead::find($leadId)) {
                continue;
            }

            $this->authorize('update', $record);

            $record->update([
                'pipeline_stage_order' => $orderNumber + 1,
            ]);
        }
    }

    public function onStageChanged($recordId, $stageId, $fromOrderedIds, $toOrderedIds)
    {
        if (! $record = Lead::find($recordId)) {
            return;
        }

        $this->authorize('update', $record);

        $record->update([
            'pipeline_stage_id' => $stageId,
        ]);

        foreach ([$fromOrderedIds, $toOrderedIds] as $orderedIds) {
            foreach ($orderedIds as $orderNumber => $leadId) {
                if (! $reordered = Lead::find($leadId)) {
                    continue;
                }

                $this->authorize('update', $reordered);

                $reordered->update([
                    'pipeline_stage_order' => $orderNumber + 1,
                ]);
            }
        }
    }

    public function records(): Collection
    {
        return $this->leads->map(function (Lead $lead) {
            return [
                'id' => $lead->id,
                'title' => $lead->title,
                'labels' => $lead->labels,
                'stage' => $lead->pipelineStage->id ?? $this->firstStageId(),
                'number' => $lead->lead_id,
                'amount' => $lead->amount,
                'currency' => $lead->currency,
            ];
        });
    }
}
