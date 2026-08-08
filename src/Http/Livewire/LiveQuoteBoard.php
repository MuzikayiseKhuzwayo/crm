<?php

namespace VentureDrake\LaravelCrm\Http\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use VentureDrake\LaravelCrm\Http\Livewire\KanbanBoard\KanbanBoard;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Quote;

class LiveQuoteBoard extends KanbanBoard
{
    use AuthorizesRequests;

    public $model = 'quote';

    public $quotes;

    public function stages(): Collection
    {
        if ($pipeline = Pipeline::where('model', get_class(new Quote))->first()) {
            return $pipeline->pipelineStages()
                ->orderBy('order')
                ->orderBy('id')
                ->get();
        }
    }

    public function onStageSorted($orderedIds)
    {
        // The id list arrives straight from the browser, so every record it names is
        // authorized in its own right before it is touched. Mirrors QuoteBoard.
        foreach ($orderedIds as $orderNumber => $quoteId) {
            if (! $record = Quote::find($quoteId)) {
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
        if (! $record = Quote::find($recordId)) {
            return;
        }

        $this->authorize('update', $record);

        $record->update([
            'pipeline_stage_id' => $stageId,
        ]);

        foreach ([$fromOrderedIds, $toOrderedIds] as $orderedIds) {
            foreach ($orderedIds as $orderNumber => $quoteId) {
                if (! $reordered = Quote::find($quoteId)) {
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
        return $this->quotes->map(function (Quote $quote) {
            return [
                'id' => $quote->id,
                'title' => $quote->title,
                'labels' => $quote->labels,
                'stage' => $quote->pipelineStage->id ?? $this->firstStageId(),
                'number' => $quote->quote_id,
                'amount' => $quote->total,
                'currency' => $quote->currency,
            ];
        });
    }
}
