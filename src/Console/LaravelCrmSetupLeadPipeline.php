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
                'hex' => '64748b',
            ],
            2 => [
                'name' => 'Connected / DM Sent',
                'hex' => '0284c7',
            ],
            3 => [
                'name' => 'Engaged / Qualified',
                'hex' => '8b5cf6',
            ],
            4 => [
                'name' => 'Call Scheduled',
                'hex' => 'f59e0b',
            ],
            5 => [
                'name' => 'Proposal Sent',
                'hex' => 'ec4899',
            ],
            6 => [
                'name' => 'Closed Won',
                'hex' => '10b981',
            ],
        ];

        foreach ($stages as $order => $stageData) {
            $stage = PipelineStage::where('pipeline_id', $pipeline->id)
                ->where('order', $order)
                ->first();

            if ($stage) {
                $stage->update([
                    'name' => $stageData['name'],
                    'hex' => $stageData['hex'],
                ]);
            } else {
                PipelineStage::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'pipeline_id' => $pipeline->id,
                    'name' => $stageData['name'],
                    'hex' => $stageData['hex'],
                    'order' => $order,
                ]);
            }
        }

        $this->info('Lead Pipeline Stages aligned successfully:');
        foreach ($pipeline->pipelineStages()->orderBy('order', 'asc')->get() as $s) {
            $this->line("  [Stage {$s->order}] ID {$s->id}: {$s->name}");
        }
    }
}
