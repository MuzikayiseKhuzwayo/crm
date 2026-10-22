<?php

use Illuminate\Database\Migrations\Migration;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $pipeline = Pipeline::where('model', Lead::class)
            ->orWhere('model', 'VentureDrake\LaravelCrm\Models\Lead')
            ->first();

        if (! $pipeline) {
            $pipeline = Pipeline::where('name', 'like', '%Lead%')->first();
        }

        if (! $pipeline) {
            $pipeline = Pipeline::create([
                'external_id' => Uuid::uuid4()->toString(),
                'name' => 'Lead Sales Funnel',
                'model' => Lead::class,
                'default' => true,
                'order' => 1,
            ]);
        }

        $stagesConfig = [
            1 => [
                'name' => 'Cold Prospect',
                'color' => '64748b',
            ],
            2 => [
                'name' => 'Connection Request Sent',
                'color' => '0ea5e9',
            ],
            3 => [
                'name' => 'Connected / DM Sent',
                'color' => '0284c7',
            ],
            4 => [
                'name' => 'Engaged / Qualified',
                'color' => '8b5cf6',
            ],
            5 => [
                'name' => 'Call Scheduled',
                'color' => 'f59e0b',
            ],
            6 => [
                'name' => 'Proposal Sent',
                'color' => 'ec4899',
            ],
            7 => [
                'name' => 'Closed Won',
                'color' => '10b981',
            ],
            8 => [
                'name' => 'Closed Lost',
                'color' => 'ef4444',
            ],
        ];

        $existingStages = PipelineStage::where('pipeline_id', $pipeline->id)
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $canonicalNames = array_map(fn ($s) => strtolower($s['name']), $stagesConfig);
        $canonicalStageIds = [];

        foreach ($stagesConfig as $order => $config) {
            $stage = $existingStages->first(function ($s) use ($config, $canonicalStageIds) {
                return ! in_array($s->id, $canonicalStageIds, true)
                    && strtolower(trim($s->name)) === strtolower($config['name']);
            });

            if (! $stage) {
                $stage = $existingStages->first(function ($s) use ($order, $canonicalNames, $canonicalStageIds) {
                    return ! in_array($s->id, $canonicalStageIds, true)
                        && ! in_array(strtolower(trim($s->name)), $canonicalNames, true)
                        && $s->order == $order;
                });
            }

            if ($stage) {
                $stage->update([
                    'name' => $config['name'],
                    'order' => $order,
                    'color' => $config['color'],
                ]);
            } else {
                $stage = PipelineStage::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'pipeline_id' => $pipeline->id,
                    'name' => $config['name'],
                    'order' => $order,
                    'color' => $config['color'],
                ]);
            }

            $canonicalStageIds[$order] = $stage->id;
        }

        $connectionRequestSentStage = PipelineStage::find($canonicalStageIds[2] ?? null);
        $connectedDmSentStage = PipelineStage::find($canonicalStageIds[3] ?? null);
        $engagedStage = PipelineStage::find($canonicalStageIds[4] ?? null);

        // Reconcile leads currently in "Connected / DM Sent" that only have completed connection-request tasks
        // (and no completed DM, Scenario, Call, or Proposal tasks) -> move to "Connection Request Sent"
        if ($connectionRequestSentStage && $connectedDmSentStage) {
            $leadsInConnected = Lead::where('pipeline_stage_id', $connectedDmSentStage->id)
                ->with('tasks')
                ->get();

            foreach ($leadsInConnected as $lead) {
                $completedTasks = $lead->tasks->filter(fn ($t) => ! is_null($t->completed_at));

                if ($completedTasks->isEmpty()) {
                    continue;
                }

                $hasPostConnectionCompleted = $completedTasks->contains(function ($task) {
                    $name = strtolower(trim($task->name ?? ''));

                    foreach ([
                        'scenario 1', 'scenario 2', 'scenario 3', 'scenario 4',
                        'accept in silence', 'introductory dm', 'intro dm', 'offer dm',
                        'send a dm', 'send an introductory dm', 'connection accepted',
                        'accepted connection', 'call', 'proposal', 'sow', 'contract',
                        'evaluation kit', 'blueprint', 'discovery',
                    ] as $kw) {
                        if (str_contains($name, $kw)) {
                            return true;
                        }
                    }

                    return false;
                });

                if (! $hasPostConnectionCompleted) {
                    $lead->update([
                        'pipeline_stage_id' => $connectionRequestSentStage->id,
                        'pipeline_stage_order' => $connectionRequestSentStage->order,
                    ]);
                }
            }
        }

        // Synchronize pipeline_stage_order for all leads attached to this pipeline
        $stages = PipelineStage::where('pipeline_id', $pipeline->id)->get();
        foreach ($stages as $stage) {
            Lead::where('pipeline_stage_id', $stage->id)->update([
                'pipeline_stage_order' => $stage->order,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No-op: preserve 8-stage pipeline integrity
    }
};
