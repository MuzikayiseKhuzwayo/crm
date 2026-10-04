<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Services\AccountRelayService;

class LaravelCrmInitRelayBaskets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:init-relay-baskets 
                            {--reset : Force re-evaluating seniority and reset all lead relay baskets}
                            {--dry-run : Preview changes without persisting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batch initialize Account Relay waterfall baskets: senior/single contacts active, secondary contacts standby';

    /**
     * Execute the console command.
     */
    public function handle(AccountRelayService $relayService): int
    {
        $this->info('Initializing Account Relay waterfall baskets across all organizations and leads...');

        $reset = (bool) $this->option('reset');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE: No changes will be written.');
        }

        $bar = $this->output->createProgressBar();
        $bar->start();

        $stats = $relayService->initializeAllBaskets($reset, function () use ($bar) {
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Solo Leads (No Company -> Active #1)', $stats['solo_leads']],
                ['Multi-Lead Company Baskets', $stats['multi_orgs']],
                ['Total Organization Leads Processed', $stats['org_leads']],
                ['Active Leads (Ready for Outreach)', $stats['total_active']],
                ['Standby Leads (In Queue Behind Active Colleague)', $stats['total_standby']],
                ['Disqualified Leads', $stats['total_disqualified']],
            ]
        );

        $this->info('Account Relay baskets successfully initialized.');

        return self::SUCCESS;
    }
}
