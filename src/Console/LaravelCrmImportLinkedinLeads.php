<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Services\NumberGeneratorService;

class LaravelCrmImportLinkedinLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:import-linkedin-leads 
                            {file : Path to the LinkedIn dataset JSON file}
                            {--user=1 : User ID to assign as owner for leads and people}
                            {--dry-run : Validate and parse dataset without persisting changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import leads from a LinkedIn profile search JSON dataset into Laravel CRM';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found at path: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Reading dataset from: {$filePath}");

        $rawJson = file_get_contents($filePath);
        $profiles = json_decode($rawJson, true);

        if (! is_array($profiles)) {
            $this->error('Failed to decode JSON or file does not contain a JSON array.');

            return self::FAILURE;
        }

        $totalProfiles = count($profiles);
        $this->info("Found {$totalProfiles} profiles in dataset.");

        $ownerId = (int) $this->option('user');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE ENABLED: No records will be written to the database.');
        }

        // Ensure LinkedIn Lead Source exists
        $leadSource = null;
        if (! $isDryRun) {
            $leadSource = LeadSource::firstOrCreate(
                ['name' => 'LinkedIn'],
                ['external_id' => Uuid::uuid4()->toString()]
            );
        }

        // Get default lead pipeline and stage
        $pipeline = Pipeline::where('model', Lead::class)->first();
        $pipelineStage = $pipeline?->pipelineStages()->orderBy('order', 'asc')->first();

        $leadsCreated = 0;
        $peopleCreated = 0;
        $organizationsCreated = 0;
        $skippedCount = 0;

        $bar = $this->output->createProgressBar($totalProfiles);
        $bar->start();

        // Process in chunks of 50 profiles
        $chunks = array_chunk($profiles, 50);

        foreach ($chunks as $chunk) {
            if ($isDryRun) {
                foreach ($chunk as $profile) {
                    $bar->advance();
                    $linkedinUrl = trim($profile['linkedinUrl'] ?? '');
                    if (empty($linkedinUrl) || Lead::where('linkedin', $linkedinUrl)->exists()) {
                        $skippedCount++;

                        continue;
                    }
                    $leadsCreated++;
                }

                continue;
            }

            DB::transaction(function () use (
                $chunk,
                $ownerId,
                $leadSource,
                $pipeline,
                $pipelineStage,
                &$leadsCreated,
                &$peopleCreated,
                &$organizationsCreated,
                &$skippedCount,
                $bar
            ) {
                foreach ($chunk as $profile) {
                    $bar->advance();

                    $linkedinUrl = trim($profile['linkedinUrl'] ?? '');
                    if (empty($linkedinUrl)) {
                        $skippedCount++;

                        continue;
                    }

                    // Deduplication check: skip if lead with this LinkedIn URL already exists
                    if (Lead::where('linkedin', $linkedinUrl)->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    // 1. Resolve or Create Organization
                    $currentPositions = $profile['currentPosition'] ?? [];
                    $pos0 = ! empty($currentPositions) && is_array($currentPositions) ? $currentPositions[0] : [];
                    $companyName = ! empty($pos0['companyName']) ? trim($pos0['companyName']) : null;
                    $companyLinkedinUrl = ! empty($pos0['companyLinkedinUrl']) ? trim($pos0['companyLinkedinUrl']) : null;
                    $position = ! empty($pos0['position']) ? trim($pos0['position']) : null;

                    $organization = null;
                    if ($companyName) {
                        $organization = Organization::where('name', $companyName)->first();
                        if (! $organization) {
                            $organization = Organization::create([
                                'external_id' => Uuid::uuid4()->toString(),
                                'name' => $companyName,
                                'linkedin' => $companyLinkedinUrl,
                                'user_owner_id' => $ownerId,
                                'user_created_id' => $ownerId,
                            ]);
                            $organizationsCreated++;
                        }
                    }

                    // 2. Create Person
                    $firstName = trim($profile['firstName'] ?? '');
                    $lastName = trim($profile['lastName'] ?? '');
                    $headline = trim($profile['headline'] ?? '');
                    $about = trim($profile['about'] ?? '');

                    $personDescription = $position && $companyName
                        ? "{$position} at {$companyName}"
                        : ($headline ?: null);

                    $person = Person::create([
                        'external_id' => Uuid::uuid4()->toString(),
                        'first_name' => $firstName ?: 'Prospect',
                        'last_name' => $lastName ?: null,
                        'description' => $personDescription,
                        'organization_id' => $organization?->id,
                        'linkedin' => $linkedinUrl,
                        'user_owner_id' => $ownerId,
                        'user_created_id' => $ownerId,
                    ]);
                    $peopleCreated++;

                    // Attach Primary Address to Person if location is available
                    $location = $profile['location'] ?? [];
                    $parsedLocation = $location['parsed'] ?? [];
                    $line1 = ! empty($location['linkedinText']) ? trim($location['linkedinText']) : null;
                    $city = ! empty($parsedLocation['city']) ? trim($parsedLocation['city']) : null;
                    $state = ! empty($parsedLocation['state']) ? trim($parsedLocation['state']) : null;
                    $country = ! empty($parsedLocation['country'])
                        ? trim($parsedLocation['country'])
                        : (! empty($location['countryCode']) ? trim($location['countryCode']) : null);

                    if ($line1 || $city || $country) {
                        $person->addresses()->create([
                            'external_id' => Uuid::uuid4()->toString(),
                            'line1' => $line1,
                            'city' => $city,
                            'state' => $state,
                            'country' => $country,
                            'primary' => 1,
                        ]);
                    }

                    // 3. Create Lead
                    if ($companyName && $position) {
                        $leadTitle = "{$companyName} - {$position}";
                    } elseif ($companyName) {
                        $leadTitle = "{$companyName} - {$firstName} {$lastName}";
                    } elseif ($position) {
                        $leadTitle = "{$firstName} {$lastName} - {$position}";
                    } else {
                        $leadTitle = "{$firstName} {$lastName} - Lead";
                    }

                    $descParts = [];
                    if ($headline) {
                        $descParts[] = "Headline: {$headline}";
                    }
                    if ($position && $companyName) {
                        $descParts[] = "Current Role: {$position} at {$companyName}";
                    }
                    if (! empty($profile['connectionsCount'])) {
                        $descParts[] = "Connections: {$profile['connectionsCount']}";
                    }
                    if (! empty($profile['followerCount'])) {
                        $descParts[] = "Followers: {$profile['followerCount']}";
                    }
                    if ($about) {
                        $descParts[] = "\nAbout:\n{$about}";
                    }
                    $leadDescription = implode("\n", $descParts);

                    Lead::create([
                        'external_id' => Uuid::uuid4()->toString(),
                        'person_id' => $person->id,
                        'organization_id' => $organization?->id,
                        'title' => $leadTitle,
                        'description' => $leadDescription,
                        'lead_source_id' => $leadSource?->id,
                        'lead_status_id' => 1,
                        'user_owner_id' => $ownerId,
                        'user_created_id' => $ownerId,
                        'linkedin' => $linkedinUrl,
                        'pipeline_id' => $pipeline?->id,
                        'pipeline_stage_id' => $pipelineStage?->id,
                        'currency' => 'USD',
                    ]);
                    $leadsCreated++;
                }
            });
        }

        $bar->finish();
        $this->newLine(2);

        if (! $isDryRun) {
            NumberGeneratorService::reset(Lead::class);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Profiles Processed', $totalProfiles],
                ['Leads Created', $leadsCreated],
                ['People Created', $peopleCreated],
                ['Organizations Created', $organizationsCreated],
                ['Skipped (Existing / Empty)', $skippedCount],
            ]
        );

        $this->info('LinkedIn leads import completed successfully!');

        return self::SUCCESS;
    }
}
