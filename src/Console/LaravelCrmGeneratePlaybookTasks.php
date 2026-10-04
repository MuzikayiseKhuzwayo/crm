<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\SalesPlaybookService;

class LaravelCrmGeneratePlaybookTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:generate-playbook-tasks
                            {--lead= : Specific Lead ID to generate playbook tasks for}
                            {--stage= : Target pipeline stage ID (default: all active leads without playbook tasks)}
                            {--limit=0 : Maximum number of leads to process (0 for unlimited)}
                            {--force : Force regenerate tasks even if a connection task already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate personalized Sales Playbook V2 tasks for leads based on role/angle detection';

    /**
     * Execute the console command.
     */
    public function handle(SalesPlaybookService $playbookService)
    {
        $this->info('Initializing Institutional Sales Motions V2 Task Generator (DUB-REV-SALES-MOTIONS-V2-002)...');

        $query = Lead::whereNull('deleted_at')->with(['person', 'organization', 'pipelineStage']);

        if ($leadId = $this->option('lead')) {
            $query->where('id', $leadId);
        } elseif ($stageId = $this->option('stage')) {
            $query->where('pipeline_stage_id', $stageId);
        }

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $leads = $query->get();
        $this->info("Evaluating {$leads->count()} candidate leads for Playbook task generation...");

        $createdCount = 0;
        $skippedCount = 0;
        $updatedCount = 0;
        $angleCounts = [
            'connection_angle_a' => 0,
            'connection_angle_b' => 0,
            'connection_angle_c' => 0,
        ];

        $progressBar = $this->output->createProgressBar($leads->count());
        $progressBar->start();

        foreach ($leads as $lead) {
            $existingPlaybookTask = Task::where('taskable_type', get_class($lead))
                ->where('taskable_id', $lead->id)
                ->where(function ($q) {
                    $q->where('name', 'LIKE', '%LinkedIn Note: Angle%')
                        ->orWhere('name', 'LIKE', '%Playbook%')
                        ->orWhere('name', 'LIKE', '%Connection Request%');
                })
                ->first();

            $force = $this->option('force');

            if ($existingPlaybookTask && ! $force) {
                $skippedCount++;
                $progressBar->advance();

                continue;
            }

            $recommendedAngle = $playbookService->detectRecommendedAngle($lead);
            $angleCounts[$recommendedAngle] = ($angleCounts[$recommendedAngle] ?? 0) + 1;

            if ($existingPlaybookTask && $force) {
                $rendered = $playbookService->renderTemplate($recommendedAngle, $lead);
                $existingPlaybookTask->update([
                    'name' => $rendered['name'],
                    'description' => $rendered['body'],
                    'due_at' => now()->addDays($rendered['due_in_days']),
                ]);
                $updatedCount++;
            } else {
                $playbookService->createPlaybookTask($lead, $recommendedAngle);
                $createdCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Leads Evaluated', $leads->count()],
                ['Tasks Created', $createdCount],
                ['Tasks Updated (Forced)', $updatedCount],
                ['Leads Skipped (Task Already Exists)', $skippedCount],
                ['Angle A (Crypto Prop / MMs)', $angleCounts['connection_angle_a'] ?? 0],
                ['Angle B (Systematic Macro)', $angleCounts['connection_angle_b'] ?? 0],
                ['Angle C (Quant Devs / Infra)', $angleCounts['connection_angle_c'] ?? 0],
            ]
        );

        $this->info('Playbook task generation completed successfully.');
    }
}
