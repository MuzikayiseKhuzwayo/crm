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

        foreach ($stages as $order => $stageData) {
            $stage = $existingStages->firstWhere('order', $order);

            if (! $stage && isset($existingStages[$order - 1])) {
                $stage = $existingStages[$order - 1];
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

        // Clean up or remap any extra stages beyond the canonical 7 stages
        $extraStages = PipelineStage::where('pipeline_id', $pipeline->id)
            ->whereNotIn('id', $canonicalStageIds)
            ->get();

        $closedLostStage = PipelineStage::where('pipeline_id', $pipeline->id)->where('order', 7)->first();
        foreach ($extraStages as $extra) {
            if ($closedLostStage) {
                Lead::where('pipeline_stage_id', $extra->id)->update([
                    'pipeline_stage_id' => $closedLostStage->id,
                    'pipeline_stage_order' => $closedLostStage->order,
                ]);
            }
            $extra->delete();
        }

        $this->info('Lead Pipeline Stages aligned successfully:');
        foreach ($pipeline->pipelineStages()->orderBy('order', 'asc')->get() as $s) {
            $this->line("  [Stage {$s->order}] ID {$s->id}: {$s->name}");
        }
    }
}
