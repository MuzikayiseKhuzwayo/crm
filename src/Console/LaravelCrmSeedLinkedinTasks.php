<?php

namespace VentureDrake\LaravelCrm\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;

class LaravelCrmSeedLinkedinTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:seed-linkedin-tasks 
                            {--force : Force execution without confirmation}
                            {--relative : Set due dates relative to today instead of fixed September dates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed LinkedIn connection and introductory DM tasks for leads without existing DM tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Scanning leads for missing LinkedIn outbound tasks...');

        $leads = Lead::whereNull('deleted_at')->get();
        $totalLeads = $leads->count();
        $tasksCreatedCount = 0;
        $leadsProcessedCount = 0;
        $skippedLeadsCount = 0;

        $isRelative = (bool) $this->option('relative');
        $connectionDueDate = $isRelative ? Carbon::now()->addDay()->setTime(9, 0, 0) : Carbon::parse('2026-09-07 09:00:00');
        $introDmDueDate = $isRelative ? Carbon::now()->addDays(3)->setTime(9, 0, 0) : Carbon::parse('2026-09-11 09:00:00');

        $bar = $this->output->createProgressBar($totalLeads);
        $bar->start();

        $chunks = $leads->chunk(100);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use (
                $chunk,
                $connectionDueDate,
                $introDmDueDate,
                &$tasksCreatedCount,
                &$leadsProcessedCount,
                &$skippedLeadsCount,
                $bar
            ) {
                foreach ($chunk as $lead) {
                    $bar->advance();

                    // Check if this lead already has a "Send a DM", "Send an Offer DM", or "Send an introductory DM" task
                    $hasDmTask = Task::where('taskable_type', get_class($lead))
                        ->where('taskable_id', $lead->id)
                        ->where(function ($query) {
                            $query->where('name', 'LIKE', '%Send a DM%')
                                ->orWhere('name', 'LIKE', '%Send an Offer DM%')
                                ->orWhere('name', 'LIKE', '%Send an introductory DM%')
                                ->orWhere('name', 'LIKE', '%introductory DM%');
                        })
                        ->exists();

                    if ($hasDmTask) {
                        $skippedLeadsCount++;

                        continue;
                    }

                    // Create Task 1: "Send a Connection Request and get accepted"
                    $connectionTaskExists = Task::where('taskable_type', get_class($lead))
                        ->where('taskable_id', $lead->id)
                        ->where('name', 'LIKE', '%Connection Request%')
                        ->exists();

                    if (! $connectionTaskExists) {
                        Task::create([
                            'external_id' => Uuid::uuid4()->toString(),
                            'name' => 'Send a Connection Request and get accepted',
                            'description' => 'Connect with prospect on LinkedIn and wait for connection request acceptance.',
                            'taskable_type' => get_class($lead),
                            'taskable_id' => $lead->id,
                            'due_at' => $connectionDueDate,
                            'user_owner_id' => $lead->user_owner_id ?: ($lead->user_created_id ?: 1),
                            'user_assigned_id' => $lead->user_assigned_id ?: ($lead->user_owner_id ?: 1),
                        ]);
                        $tasksCreatedCount++;
                    }

                    // Create Task 2: "Send an introductory DM"
                    $introDmTaskExists = Task::where('taskable_type', get_class($lead))
                        ->where('taskable_id', $lead->id)
                        ->where('name', 'LIKE', '%Send an introductory DM%')
                        ->exists();

                    if (! $introDmTaskExists) {
                        Task::create([
                            'external_id' => Uuid::uuid4()->toString(),
                            'name' => 'Send an introductory DM',
                            'description' => 'Send introductory message via LinkedIn DM once connection request is accepted.',
                            'taskable_type' => get_class($lead),
                            'taskable_id' => $lead->id,
                            'due_at' => $introDmDueDate,
                            'user_owner_id' => $lead->user_owner_id ?: ($lead->user_created_id ?: 1),
                            'user_assigned_id' => $lead->user_assigned_id ?: ($lead->user_owner_id ?: 1),
                        ]);
                        $tasksCreatedCount++;
                    }

                    $leadsProcessedCount++;
                }
            });
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Successfully processed {$leadsProcessedCount} leads.");
        $this->info("Created {$tasksCreatedCount} LinkedIn tasks.");
        if ($skippedLeadsCount > 0) {
            $this->comment("Skipped {$skippedLeadsCount} leads that already had DM tasks.");
        }
    }
}
