<?php

namespace VentureDrake\LaravelCrm\Http\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use VentureDrake\LaravelCrm\Http\Livewire\KanbanBoard\KanbanBoard;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Pipeline;

class LiveDealBoard extends KanbanBoard
{
    use AuthorizesRequests;

    public $model = 'deal';

    public $deals;

    public function stages(): Collection
    {
        if ($pipeline = Pipeline::where('model', get_class(new Deal))->first()) {
            return $pipeline->pipelineStages()
                ->orderBy('order')
                ->orderBy('id')
                ->get();
        }
    }

    public function onStageSorted($orderedIds)
    {
        // The id list arrives straight from the browser, so every record it names is
        // authorized in its own right before it is touched. Mirrors DealBoard.
        foreach ($orderedIds as $orderNumber => $dealId) {
            if (! $record = Deal::find($dealId)) {
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
        if (! $record = Deal::find($recordId)) {
            return;
        }

        $this->authorize('update', $record);

        $record->update([
            'pipeline_stage_id' => $stageId,
        ]);

        foreach ([$fromOrderedIds, $toOrderedIds] as $orderedIds) {
            foreach ($orderedIds as $orderNumber => $dealId) {
                if (! $reordered = Deal::find($dealId)) {
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
        return $this->deals->map(function (Deal $deal) {
            return [
                'id' => $deal->id,
                'title' => $deal->title,
                'labels' => $deal->labels,
                'stage' => $deal->pipelineStage->id ?? $this->firstStageId(),
                'number' => $deal->deal_id,
                'amount' => $deal->amount,
                'currency' => $deal->currency,
            ];
        });
    }
}
