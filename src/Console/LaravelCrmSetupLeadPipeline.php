<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;

class LaravelCrmSetupLeadPipeline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:setup-lead-pipeline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup and align the Lead Pipeline stages for LinkedIn Outbound Sales Funnel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Validating and setting up Lead Outbound Sales Pipeline stages...');

        $pipeline = Pipeline::where('model', get_class(new Lead))->first();

        if (! $pipeline) {
            $pipeline = Pipeline::create([
                'external_id' => Uuid::uuid4()->toString(),
                'name' => 'Lead Sales Funnel',
                'model' => get_class(new Lead),
                'default' => true,
                'order' => 1,
            ]);
        }

        $stages = [
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

        $canonicalNames = array_map(fn ($s) => strtolower($s['name']), $stages);
        $canonicalStageIds = [];

        foreach ($stages as $order => $stageData) {
            // 1. Prefer matching existing stage by name so existing stage IDs are preserved
            $stage = $existingStages->first(function ($s) use ($stageData, $canonicalStageIds) {
                return ! in_array($s->id, $canonicalStageIds, true)
                    && strtolower(trim($s->name)) === strtolower($stageData['name']);
            });

            // 2. Fallback to an unmapped legacy stage (one whose name is not another canonical stage)
            if (! $stage) {
                $stage = $existingStages->first(function ($s) use ($order, $canonicalNames, $canonicalStageIds) {
                    return ! in_array($s->id, $canonicalStageIds, true)
                        && ! in_array(strtolower(trim($s->name)), $canonicalNames, true)
                        && $s->order == $order;
                });
            }

            if ($stage) {
                $stage->update([
                    'name' => $stageData['name'],
                    'order' => $order,
                    'color' => $stageData['color'],
                ]);
            } else {
                $stage = PipelineStage::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'pipeline_id' => $pipeline->id,
                    'name' => $stageData['name'],
                    'color' => $stageData['color'],
                    'order' => $order,
                ]);
            }

            $canonicalStageIds[] = $stage->id;
        }

        // Clean up or remap any extra stages beyond the canonical 8 stages
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
        foreach ($pipeline->pipelineStages()->orderBy('order', 'asc')->get() as $s) {
            Lead::where('pipeline_stage_id', $s->id)->update([
                'pipeline_stage_order' => $s->order,
            ]);
        }

        $this->info('Lead Pipeline Stages aligned successfully:');
        foreach ($pipeline->pipelineStages()->orderBy('order', 'asc')->get() as $s) {
            $this->line("  [Stage {$s->order}] ID {$s->id}: {$s->name}");
        }
    }
}
