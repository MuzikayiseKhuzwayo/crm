<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Services\AccountRelayService;

class LaravelCrmRotateAccountRelay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:rotate-account-relay
                            {--days=14 : Days of inactivity before rotating to next colleague}
                            {--company= : Specific company ID or external ID to rotate}
                            {--dry-run : Preview rotations without modifying records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate account outreach relay to the next colleague for leads that fell off';

    /**
     * Execute the console command.
     */
    public function handle(AccountRelayService $relayService): int
    {
        $days = (int) $this->option('days');
        $companyFilter = $this->option('company');
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("Scanning account outreach cadences (Threshold: {$days} days)...");

        if ($isDryRun) {
            $this->warn('DRY RUN MODE ENABLED: No database records will be modified.');
        }

        if ($companyFilter) {
            $org = is_numeric($companyFilter)
                ? Organization::find($companyFilter)
                : Organization::where('external_id', $companyFilter)->first();

            if (! $org) {
                $this->error("Organization not found: {$companyFilter}");

                return self::FAILURE;
            }

            $activeLead = $org->leads()->where('relay_status', 'active')->first();
            if (! $activeLead) {
                $this->warn("No active lead currently in relay for {$org->name}. Initializing basket...");
                if (! $isDryRun) {
                    $relayService->initializeOrganizationBasket($org);
                }
                $this->info("Initialized relay basket for {$org->name}.");

                return self::SUCCESS;
            }

            $this->info("Rotating lead {$activeLead->title} at {$org->name}...");
            if (! $isDryRun) {
                $next = $relayService->rotateToNext($activeLead, 'manual rotation via CLI');
                if ($next) {
                    $this->info("Next lead activated: {$next->title} (ID: {$next->id})");
                } else {
                    $this->warn("No more leads on standby for {$org->name}. Account basket exhausted.");
                }
            }

            return self::SUCCESS;
        }

        if ($isDryRun) {
            $cutoff = now()->subDays($days);
            $dueLeads = Lead::where('relay_status', 'active')
                ->whereNotNull('organization_id')
                ->where('relay_activated_at', '<=', $cutoff)
                ->get();

            $this->info("Found {$dueLeads->count()} active lead(s) due for rotation.");
            foreach ($dueLeads as $l) {
                $this->line(" - [Lead {$l->id}] {$l->title} at {$l->organization?->name} (Active since {$l->relay_activated_at?->diffForHumans()})");
            }

            return self::SUCCESS;
        }

        $rotations = $relayService->autoRotateDueLeads($days);

        if (empty($rotations)) {
            $this->info('No leads currently due for rotation.');

            return self::SUCCESS;
        }

        $this->info('Completed '.count($rotations).' account relay rotation(s):');
        $rows = [];
        foreach ($rotations as $r) {
            $rows[] = [
                $r['company'],
                $r['old_lead_id'],
                $r['new_lead_id'] ? "Lead {$r['new_lead_id']}" : 'Exhausted',
            ];
        }

        $this->table(['Company', 'Previous Lead', 'New Active Lead'], $rows);

        return self::SUCCESS;
    }
}
