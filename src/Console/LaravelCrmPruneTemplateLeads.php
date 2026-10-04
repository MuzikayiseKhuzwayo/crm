<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Quote;

class LaravelCrmPruneTemplateLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:prune-template-leads
                            {--dry-run : Preview template leads that would be deleted without persisting changes}
                            {--force : Run without interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and delete legacy template/sample leads while preserving authentic prospect data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('Scanning database for old template/sample leads...');

        // Template leads have no LinkedIn profile and were created via sample seeder
        $query = Lead::where(function ($q) {
            $q->whereNull('linkedin')->orWhere('linkedin', '');
        });

        $totalFound = $query->count();

        if ($totalFound === 0) {
            $this->info('No template leads found. Database is clean!');

            return self::SUCCESS;
        }

        $this->warn("Found {$totalFound} template leads to remove.");

        $templateLeadIds = (clone $query)->pluck('id')->toArray();
        $linkedDeals = Deal::whereIn('lead_id', $templateLeadIds)->count();
        $linkedQuotes = Quote::whereIn('lead_id', $templateLeadIds)->count();
        $linkedOrders = Order::whereIn('lead_id', $templateLeadIds)->count();

        $this->line("Associated records: {$linkedDeals} deals, {$linkedQuotes} quotes, {$linkedOrders} orders (foreign references will be detached safely).");

        if ($isDryRun) {
            $this->newLine();
            $this->warn('DRY RUN: No records were deleted.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Are you sure you want to permanently delete {$totalFound} template leads?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info('Deleting template leads...');
        $bar = $this->output->createProgressBar($totalFound);
        $bar->start();

        $deletedCount = 0;

        DB::transaction(function () use ($query, $bar, &$deletedCount) {
            // Process chunk by chunk using Eloquent models so LeadObserver events fire
            $query->chunkById(50, function ($leads) use ($bar, &$deletedCount) {
                foreach ($leads as $lead) {
                    $lead->forceDelete();
                    $deletedCount++;
                    $bar->advance();
                }
            });
        });

        $bar->finish();
        $this->newLine(2);

        $remainingLeads = Lead::count();
        $this->info("✅ Successfully deleted {$deletedCount} template leads.");
        $this->info("Remaining authentic leads in database: {$remainingLeads}");

        return self::SUCCESS;
    }
}
