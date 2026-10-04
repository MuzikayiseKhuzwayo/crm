<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Address;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\NumberGeneratorService;

class LaravelCrmSyncLeadsFromSqlite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:sync-leads-from-sqlite
                            {--sqlite-path= : Path to sqlite database file (defaults to database/database.sqlite)}
                            {--user=1 : User ID to assign as owner for synced leads and contacts}
                            {--dry-run : Validate and preview records without persisting changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync leads, people, organizations, addresses, and tasks from local SQLite database into production database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $sqlitePath = $this->option('sqlite-path');
        if (! $sqlitePath) {
            $candidates = [
                base_path('database/database.sqlite'),
                dirname(__DIR__, 2).'/database/database.sqlite',
                base_path('../../../../database/database.sqlite'),
            ];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $sqlitePath = $cand;
                    break;
                }
            }
            $sqlitePath = $sqlitePath ?: base_path('database/database.sqlite');
        }

        if (! file_exists($sqlitePath)) {
            $this->error("SQLite database file not found at: {$sqlitePath}");

            return self::FAILURE;
        }

        $this->info("Connecting to source SQLite database: {$sqlitePath}");
        $sqlite = new PDO("sqlite:{$sqlitePath}");
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $ownerId = (int) $this->option('user');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE ENABLED: No changes will be written to target database.');
        }

        // 1. Ensure target LinkedIn Lead Source exists
        $leadSource = null;
        if (! $isDryRun) {
            $leadSource = LeadSource::firstOrCreate(
                ['name' => 'LinkedIn'],
                ['external_id' => Uuid::uuid4()->toString()]
            );
        }

        // 2. Resolve target Pipeline & Stages
        $pipeline = Pipeline::where('model', Lead::class)->first();
        $targetStages = $pipeline?->pipelineStages()->orderBy('order', 'asc')->get() ?: collect();
        $defaultStage = $targetStages->first();

        // 3. Load all Leads from SQLite
        $this->info('Reading leads from SQLite...');
        $stmt = $sqlite->query('SELECT * FROM crm_leads ORDER BY id ASC');
        $sqliteLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Exclude soft-deleted leads if deleted_at column is present
        $sqliteLeads = array_values(array_filter($sqliteLeads, fn ($row) => empty($row['deleted_at'])));
        $totalLeads = count($sqliteLeads);
        $this->info("Found {$totalLeads} total leads in SQLite.");

        // Maps to associate SQLite IDs with target DB models
        $orgMap = [];    // sqlite_org_id => target Organization
        $personMap = []; // sqlite_person_id => target Person
        $leadMap = [];   // sqlite_lead_id => target Lead

        $leadsCreated = 0;
        $leadsSkipped = 0;
        $peopleCreated = 0;
        $orgsCreated = 0;
        $tasksCreated = 0;

        $bar = $this->output->createProgressBar($totalLeads);
        $bar->start();

        // Process leads in chunks of 50
        $chunks = array_chunk($sqliteLeads, 50);

        foreach ($chunks as $chunk) {
            if ($isDryRun) {
                foreach ($chunk as $row) {
                    $bar->advance();
                    $linkedinUrl = trim($row['linkedin'] ?? '');
                    if (! empty($linkedinUrl) && Lead::where('linkedin', $linkedinUrl)->exists()) {
                        $leadsSkipped++;
                    } else {
                        $leadsCreated++;
                    }
                }

                continue;
            }

            DB::transaction(function () use (
                $chunk,
                $sqlite,
                $ownerId,
                $leadSource,
                $pipeline,
                $targetStages,
                $defaultStage,
                &$orgMap,
                &$personMap,
                &$leadMap,
                &$leadsCreated,
                &$leadsSkipped,
                &$peopleCreated,
                &$orgsCreated,
                $bar
            ) {
                foreach ($chunk as $row) {
                    $bar->advance();

                    $linkedinUrl = trim($row['linkedin'] ?? '');
                    $externalId = trim($row['external_id'] ?? '');

                    // Check if lead already exists in target DB
                    $existingLead = null;
                    if (! empty($linkedinUrl)) {
                        $existingLead = Lead::where('linkedin', $linkedinUrl)->first();
                    }
                    if (! $existingLead && ! empty($externalId)) {
                        $existingLead = Lead::where('external_id', $externalId)->first();
                    }

                    if ($existingLead) {
                        $leadMap[$row['id']] = $existingLead;
                        $leadsSkipped++;

                        continue;
                    }

                    // 1. Resolve or Create Organization
                    $targetOrg = null;
                    if (! empty($row['organization_id'])) {
                        $sqliteOrgId = $row['organization_id'];
                        if (isset($orgMap[$sqliteOrgId])) {
                            $targetOrg = $orgMap[$sqliteOrgId];
                        } else {
                            $orgStmt = $sqlite->prepare('SELECT * FROM crm_organizations WHERE id = ?');
                            $orgStmt->execute([$sqliteOrgId]);
                            $sqliteOrg = $orgStmt->fetch(PDO::FETCH_ASSOC);

                            if ($sqliteOrg && ! empty($sqliteOrg['name'])) {
                                $targetOrg = Organization::where('name', $sqliteOrg['name'])->first();
                                if (! $targetOrg) {
                                    $targetOrg = Organization::create([
                                        'external_id' => $sqliteOrg['external_id'] ?: Uuid::uuid4()->toString(),
                                        'name' => $sqliteOrg['name'],
                                        'linkedin' => $sqliteOrg['linkedin'] ?? null,
                                        'user_owner_id' => $ownerId,
                                        'user_created_id' => $ownerId,
                                    ]);
                                    $orgsCreated++;
                                }
                                $orgMap[$sqliteOrgId] = $targetOrg;
                            }
                        }
                    }

                    // 2. Resolve or Create Person
                    $targetPerson = null;
                    if (! empty($row['person_id'])) {
                        $sqlitePersonId = $row['person_id'];
                        if (isset($personMap[$sqlitePersonId])) {
                            $targetPerson = $personMap[$sqlitePersonId];
                        } else {
                            $personStmt = $sqlite->prepare('SELECT * FROM crm_people WHERE id = ?');
                            $personStmt->execute([$sqlitePersonId]);
                            $sqlitePerson = $personStmt->fetch(PDO::FETCH_ASSOC);

                            if ($sqlitePerson) {
                                $pLinkedin = trim($sqlitePerson['linkedin'] ?? '');
                                if (! empty($pLinkedin)) {
                                    $targetPerson = Person::where('linkedin', $pLinkedin)->first();
                                }
                                if (! $targetPerson && ! empty($sqlitePerson['external_id'])) {
                                    $targetPerson = Person::where('external_id', $sqlitePerson['external_id'])->first();
                                }

                                if (! $targetPerson) {
                                    $targetPerson = Person::create([
                                        'external_id' => $sqlitePerson['external_id'] ?: Uuid::uuid4()->toString(),
                                        'first_name' => $sqlitePerson['first_name'] ?: 'Prospect',
                                        'last_name' => $sqlitePerson['last_name'] ?? null,
                                        'description' => $sqlitePerson['description'] ?? null,
                                        'organization_id' => $targetOrg?->id,
                                        'linkedin' => $pLinkedin ?: null,
                                        'user_owner_id' => $ownerId,
                                        'user_created_id' => $ownerId,
                                    ]);
                                    $peopleCreated++;

                                    // Check if Person had primary address in SQLite
                                    $addrStmt = $sqlite->prepare("SELECT * FROM crm_addresses WHERE addressable_type LIKE '%Person%' AND addressable_id = ? AND \"primary\" = 1 LIMIT 1");
                                    $addrStmt->execute([$sqlitePersonId]);
                                    $sqliteAddr = $addrStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($sqliteAddr) {
                                        $targetPerson->addresses()->create([
                                            'external_id' => Uuid::uuid4()->toString(),
                                            'line1' => $sqliteAddr['line1'] ?? null,
                                            'city' => $sqliteAddr['city'] ?? null,
                                            'state' => $sqliteAddr['state'] ?? null,
                                            'country' => $sqliteAddr['country'] ?? null,
                                            'primary' => 1,
                                        ]);
                                    }
                                }
                                $personMap[$sqlitePersonId] = $targetPerson;
                            }
                        }
                    }

                    // 3. Resolve Target Stage
                    $stageId = $defaultStage?->id;
                    if (! empty($row['pipeline_stage_id'])) {
                        // Check if stage exists by order or id
                        $matchedStage = $targetStages->firstWhere('id', $row['pipeline_stage_id'])
                            ?: ($targetStages->firstWhere('order', $row['pipeline_stage_order'] ?? 1) ?: $defaultStage);
                        if ($matchedStage) {
                            $stageId = $matchedStage->id;
                        }
                    }

                    // 4. Create Lead in Target DB
                    $newLead = Lead::create([
                        'external_id' => ! empty($row['external_id']) ? $row['external_id'] : Uuid::uuid4()->toString(),
                        'person_id' => $targetPerson?->id,
                        'organization_id' => $targetOrg?->id,
                        'title' => $row['title'] ?? 'Lead',
                        'description' => $row['description'] ?? null,
                        'lead_source_id' => $leadSource?->id ?: ($row['lead_source_id'] ?? null),
                        'lead_status_id' => $row['lead_status_id'] ?? 1,
                        'user_owner_id' => $ownerId,
                        'user_created_id' => $ownerId,
                        'linkedin' => $linkedinUrl ?: null,
                        'pipeline_id' => $pipeline?->id,
                        'pipeline_stage_id' => $stageId,
                        'currency' => $row['currency'] ?? 'USD',
                        'created_at' => $row['created_at'] ?? now(),
                        'updated_at' => $row['updated_at'] ?? now(),
                    ]);

                    $leadMap[$row['id']] = $newLead;
                    $leadsCreated++;
                }
            });
        }

        $bar->finish();
        $this->newLine(2);

        // 4. Sync Tasks attached to Leads from SQLite
        $this->info('Syncing tasks for leads from SQLite...');
        $taskStmt = $sqlite->query("SELECT * FROM crm_tasks WHERE taskable_type LIKE '%Lead%' ORDER BY id ASC");
        $sqliteTasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
        $taskCount = count($sqliteTasks);
        $this->info("Found {$taskCount} tasks in SQLite.");

        if (! $isDryRun && ! empty($sqliteTasks)) {
            $taskBar = $this->output->createProgressBar(count($sqliteTasks));
            $taskBar->start();

            $taskChunks = array_chunk($sqliteTasks, 100);
            foreach ($taskChunks as $tChunk) {
                DB::transaction(function () use ($tChunk, $leadMap, &$tasksCreated, $taskBar, $ownerId) {
                    foreach ($tChunk as $tRow) {
                        $taskBar->advance();
                        $sqliteLeadId = $tRow['taskable_id'];
                        $targetLead = $leadMap[$sqliteLeadId] ?? null;

                        if (! $targetLead) {
                            $targetLead = Lead::find($sqliteLeadId);
                        }

                        if (! $targetLead) {
                            continue;
                        }

                        $taskExists = Task::where('taskable_type', get_class($targetLead))
                            ->where('taskable_id', $targetLead->id)
                            ->where('name', $tRow['name'])
                            ->exists();

                        if (! $taskExists) {
                            Task::create([
                                'external_id' => $tRow['external_id'] ?: Uuid::uuid4()->toString(),
                                'name' => $tRow['name'],
                                'description' => $tRow['description'] ?? null,
                                'taskable_type' => get_class($targetLead),
                                'taskable_id' => $targetLead->id,
                                'due_at' => $tRow['due_at'] ?? null,
                                'completed_at' => $tRow['completed_at'] ?? null,
                                'user_owner_id' => $ownerId,
                                'user_assigned_id' => $ownerId,
                                'created_at' => $tRow['created_at'] ?? now(),
                                'updated_at' => $tRow['updated_at'] ?? now(),
                            ]);
                            $tasksCreated++;
                        }
                    }
                });
            }

            $taskBar->finish();
            $this->newLine(2);
        }

        if (! $isDryRun) {
            NumberGeneratorService::reset(Lead::class);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Leads in SQLite', $totalLeads],
                ['Leads Created in Target DB', $leadsCreated],
                ['Leads Already Present (Skipped)', $leadsSkipped],
                ['People Created', $peopleCreated],
                ['Organizations Created', $orgsCreated],
                ['Tasks Created', $tasksCreated],
            ]
        );

        $this->info('Database sync from SQLite completed successfully!');

        return self::SUCCESS;
    }
}
