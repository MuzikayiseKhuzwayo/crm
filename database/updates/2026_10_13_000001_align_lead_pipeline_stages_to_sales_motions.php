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
                'name' => 'Connected / DM Sent',
                'color' => '0284c7',
            ],
            3 => [
                'name' => 'Engaged / Qualified',
                'color' => '8b5cf6',
            ],
            4 => [
                'name' => 'Call Scheduled',
                'color' => 'f59e0b',
            ],
            5 => [
                'name' => 'Proposal Sent',
                'color' => 'ec4899',
            ],
            6 => [
                'name' => 'Closed Won',
                'color' => '10b981',
            ],
            7 => [
                'name' => 'Closed Lost',
                'color' => 'ef4444',
            ],
        ];

        $existingStages = PipelineStage::where('pipeline_id', $pipeline->id)
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $canonicalStageIds = [];

        foreach ($stagesConfig as $order => $config) {
            // Match existing stage by order, or match sequentially from existing stages collection
            $stage = $existingStages->firstWhere('order', $order);

            if (! $stage && isset($existingStages[$order - 1])) {
                $stage = $existingStages[$order - 1];
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

            $canonicalStageIds[] = $stage->id;
        }

        // Remap and clean up any extra stages beyond the canonical 7 stages
        $extraStages = PipelineStage::where('pipeline_id', $pipeline->id)
            ->whereNotIn('id', $canonicalStageIds)
            ->get();

        $coldProspectStage = PipelineStage::where('pipeline_id', $pipeline->id)->where('order', 1)->first();
        foreach ($extraStages as $extra) {
            if ($coldProspectStage) {
                Lead::where('pipeline_stage_id', $extra->id)->update([
                    'pipeline_stage_id' => $coldProspectStage->id,
                    'pipeline_stage_order' => $coldProspectStage->order,
                ]);
            }
            $extra->delete();
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
        $pipeline = Pipeline::where('model', Lead::class)
            ->orWhere('model', 'VentureDrake\LaravelCrm\Models\Lead')
            ->first();

        if (! $pipeline) {
            return;
        }

        $legacyStages = [
            1 => 'New',
            2 => 'Appointment Scheduled',
            3 => 'Qualified To Buy',
            4 => 'Presentation Scheduled',
            5 => 'Decision Maker Bought-In',
            6 => 'Contract Sent',
            7 => 'Closed Won',
        ];

        foreach ($legacyStages as $order => $name) {
            $stage = PipelineStage::where('pipeline_id', $pipeline->id)->where('order', $order)->first();
            if ($stage) {
                $stage->update(['name' => $name]);
            }
        }
    }
};
