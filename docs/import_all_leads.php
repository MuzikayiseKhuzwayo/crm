<?php

require __DIR__ . '/../vendor/autoload.php';

$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'laravel_crm';
$_ENV['DB_USERNAME'] = 'crm_user';
$_ENV['DB_PASSWORD'] = 'SecurePass123!';

putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_DATABASE=laravel_crm');
putenv('DB_USERNAME=crm_user');
putenv('DB_PASSWORD=SecurePass123!');

$app = require __DIR__ . '/../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$app->register(\VentureDrake\LaravelCrm\LaravelCrmServiceProvider::class);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

config(['laravel-crm.db_table_prefix' => 'crm_']);

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Address;
use VentureDrake\LaravelCrm\Models\AddressType;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\Label;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Note;

$userModel = config('auth.providers.users.model') ?? \App\Models\User::class;
$owner = $userModel::first();
$userId = $owner ? $owner->id : 1;

$jsonFile = __DIR__ . '/dataset.json';
if (!file_exists($jsonFile)) {
    die("JSON dataset file not found: $jsonFile\n");
}

$profiles = json_decode(file_get_contents($jsonFile), true);
if (!$profiles) {
    die("Failed to parse JSON dataset.\n");
}

echo "Loaded " . count($profiles) . " profiles from dataset.\n";

// Get or create Lead Source
$leadSource = LeadSource::firstOrCreate(['name' => 'LinkedIn Sourced Leads'], [
    'external_id' => Str::uuid()->toString(),
]);

// Get or create Label
$labelQuant = Label::firstOrCreate(['name' => 'LinkedIn Lead'], [
    'external_id' => Str::uuid()->toString(),
    'hex' => '#3B82F6',
]);

// Get Lead Pipeline & Stage
$leadPipeline = Pipeline::where('model', Lead::class)->first() ?? Pipeline::first();
$leadStages = PipelineStage::where('pipeline_id', $leadPipeline->id)->get();
$leadStageNew = $leadStages->firstWhere('name', 'New') ?? $leadStages->first();
$leadStageQual = $leadStages->firstWhere('name', 'Qualified To Buy') ?? $leadStages->skip(1)->first();

$addressType = AddressType::where('name', 'Primary')->first() ?? AddressType::first();

$maxLeadNum = Lead::max('number') ?? 1000;
$leadIdx = $maxLeadNum + 1;

$insertedCount = 0;
$skippedCount = 0;

foreach ($profiles as $p) {
    $firstName = trim($p['firstName'] ?? '');
    $lastName = trim($p['lastName'] ?? '');
    $headline = trim($p['headline'] ?? '');
    $linkedin = trim($p['linkedinUrl'] ?? '');

    if (empty($firstName) && empty($lastName)) {
        continue;
    }

    // Check if person already exists by first and last name
    $existingPerson = Person::where('first_name', $firstName)
        ->where('last_name', $lastName)
        ->first();

    if ($existingPerson) {
        $skippedCount++;
        echo "Skipping existing lead: {$firstName} {$lastName}\n";
        continue;
    }

    // Location
    $locData = $p['location'] ?? [];
    $location = $locData['linkedinText'] ?? ($locData['parsed']['text'] ?? 'Global');

    // Current Position & Company
    $curPosList = $p['currentPosition'] ?? [];
    if (!empty($curPosList)) {
        $companyName = trim($curPosList[0]['companyName'] ?? 'Independent / Stealth');
        $jobPos = trim($curPosList[0]['position'] ?? ($headline ?: 'Executive'));
    } else {
        $companyName = 'Independent / Stealth';
        $jobPos = $headline ?: 'Executive';
    }

    $leadTitle = trim("{$firstName} {$lastName} - {$companyName} - " . substr($jobPos, 0, 50));
    $about = trim($p['about'] ?? '');
    $summary = !empty($about) ? substr($about, 0, 250) : "{$jobPos} at {$companyName}. Located in {$location}.";

    // Notes
    $notesLines = [
        "LinkedIn Profile: {$linkedin}",
        "Headline: {$headline}",
        "Connections: " . ($p['connectionsCount'] ?? 0) . " | Followers: " . ($p['followerCount'] ?? 0),
        "Location: {$location}",
        ""
    ];

    if (!empty($about)) {
        $notesLines[] = "About:";
        $notesLines[] = $about;
        $notesLines[] = "";
    }

    $expList = $p['experience'] ?? [];
    if (!empty($expList)) {
        $notesLines[] = "Experience:";
        foreach (array_slice($expList, 0, 6) as $exp) {
            $pos = $exp['position'] ?? '';
            $comp = $exp['companyName'] ?? '';
            $start = $exp['startDate']['text'] ?? '';
            $end = $exp['endDate']['text'] ?? '';
            $timeStr = $start ? " ({$start} - {$end})" : "";
            $notesLines[] = "- {$pos} @ {$comp}{$timeStr}";
        }
        $notesLines[] = "";
    }

    $eduList = $p['education'] ?? [];
    if (!empty($eduList)) {
        $notesLines[] = "Education:";
        foreach (array_slice($eduList, 0, 4) as $edu) {
            $school = $edu['schoolName'] ?? '';
            $degree = $edu['degree'] ?? '';
            $field = $edu['fieldOfStudy'] ?? '';
            $degStr = ($degree || $field) ? " ({$degree} - {$field})" : "";
            $notesLines[] = "- {$school}{$degStr}";
        }
        $notesLines[] = "";
    }

    $certList = $p['certifications'] ?? [];
    if (!empty($certList)) {
        $notesLines[] = "Certifications:";
        foreach (array_slice($certList, 0, 6) as $cert) {
            $ctitle = $cert['title'] ?? '';
            $by = $cert['issuedBy'] ?? '';
            $notesLines[] = "- {$ctitle} ({$by})";
        }
        $notesLines[] = "";
    }

    $skillsList = $p['skills'] ?? [];
    if (!empty($skillsList)) {
        $skNames = array_filter(array_column($skillsList, 'name'));
        if (!empty($skNames)) {
            $notesLines[] = "Top Skills:\n" . implode(', ', array_slice($skNames, 0, 15));
        }
    }

    $notesText = implode("\n", $notesLines);

    // Deal estimation
    $amount = 6500.00;
    if (preg_match('/trading|hedge|capital|director|founder|vp|head/i', $companyName . ' ' . $headline)) {
        $amount = 12500.00;
    } elseif (preg_match('/intern|assistant|analyst/i', $jobPos)) {
        $amount = 3500.00;
    }

    // 1. Organization
    $org = Organization::firstOrCreate(
        ['name' => $companyName],
        [
            'external_id' => Str::uuid()->toString(),
            'user_owner_id' => $userId,
        ]
    );

    // 2. Person
    $person = Person::create([
        'external_id' => Str::uuid()->toString(),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'job_title' => $headline,
        'organization_id' => $org->id,
        'user_owner_id' => $userId,
    ]);

    // 3. Address
    if ($addressType) {
        Address::create([
            'external_id' => Str::uuid()->toString(),
            'address_type_id' => $addressType->id,
            'address' => $location,
            'primary' => true,
            'addressable_type' => Person::class,
            'addressable_id' => $person->id,
        ]);
    }

    // 4. Lead
    $num = $leadIdx++;
    $lead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'lead_id' => 'TFA-L' . $num,
        'prefix' => 'TFA-L',
        'number' => $num,
        'title' => $leadTitle,
        'description' => $summary,
        'amount' => $amount,
        'currency' => 'USD',
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'lead_source_id' => $leadSource->id,
        'pipeline_id' => $leadPipeline->id,
        'pipeline_stage_id' => $leadStageNew->id,
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
        'user_assigned_id' => $userId,
    ]);

    if ($labelQuant) {
        $lead->labels()->attach($labelQuant->id);
    }

    // 5. Detailed Background Note
    Note::create([
        'external_id' => Str::uuid()->toString(),
        'content' => $notesText,
        'noteable_type' => Lead::class,
        'noteable_id' => $lead->id,
        'user_created_id' => $userId,
        'user_updated_id' => $userId,
    ]);

    $insertedCount++;
    echo "Created Lead TFA-L{$num}: {$leadTitle}\n";
}

echo "Done! Successfully inserted {$insertedCount} new leads into the database (Skipped {$skippedCount} existing).\n";
