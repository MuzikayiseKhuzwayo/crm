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
use Carbon\Carbon;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Contact;
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

// Get max lead number
$maxLeadNum = Lead::max('number') ?? 1000;
$leadIdx = $maxLeadNum + 1;

$leadsPayload = [
    [
        'first_name' => 'Alex',
        'last_name' => 'Lokhov',
        'headline' => 'Data Strategy and Sourcing @ Jump Trading.',
        'org_name' => 'Jump Trading Group',
        'location' => 'London, England, United Kingdom',
        'linkedin' => 'https://www.linkedin.com/in/alex-lokhov-7608161',
        'amount' => 6500.00,
        'stage_id' => $leadStageQual->id,
        'title' => 'Alex Lokhov - Jump Trading Group - Data Strategy and Sourcing',
        'summary' => 'Data Strategy and Sourcing at Jump Trading Group. MSc Computer Science from University of Edinburgh. Experienced in alternative data sourcing, research management, and pre-sales engineering.',
        'notes' => "LinkedIn Profile: https://www.linkedin.com/in/alex-lokhov-7608161\nHeadline: Data Strategy and Sourcing @ Jump Trading.\nConnections: 2,783 | Followers: 2,792\nLocation: London, England, United Kingdom\n\nExperience:\n- Data Strategy and Sourcing @ Jump Trading Group (Mar 2025 - Present)\n- Head of Research @ Hatched Analytics (Sep 2022 - May 2024)\n- Founding Partner @ Adversus Capital Ltd (Aug 2018 - Sep 2022)\n- Principal Sales Engineer @ Tableau Software (Jan 2016 - Aug 2020)\n- Senior SE @ EMC (Jul 2012 - Dec 2015)\n\nEducation:\n- MSc Computer Science, The University of Edinburgh (2005 - 2006)\n\nCertifications:\n- dbt Certified Analytics Engineer (dbt Labs, Nov 2024)\n\nTop Skills:\nAnalytics, Consultative Selling, Alternative Data, Cloud Computing, Virtualization, Storage, Machine Learning, Data Science."
    ],
    [
        'first_name' => 'Muyun',
        'last_name' => 'Chu',
        'headline' => 'EssilorLuxottica EMEA Logistics Sourcing Data analyst',
        'org_name' => 'EssilorLuxottica',
        'location' => 'Paris, Île-de-France, France',
        'linkedin' => 'https://www.linkedin.com/in/muyun-chu233',
        'amount' => 3500.00,
        'stage_id' => $leadStageNew->id,
        'title' => 'Muyun Chu - EssilorLuxottica - EMEA Logistics Sourcing Data Analyst',
        'summary' => 'EMEA Logistics Sourcing Data Analyst at EssilorLuxottica. Data Science graduate from emlyon business school and McGill University with AWS Machine Learning Specialty certification.',
        'notes' => "LinkedIn Profile: https://www.linkedin.com/in/muyun-chu233\nHeadline: EssilorLuxottica EMEA Logistics Sourcing Data analyst\nConnections: 550 | Open To Work: Yes\nLocation: Paris, Île-de-France, France\n\nExperience:\n- EMEA Logistics Sourcing Data analyst @ EssilorLuxottica (Mar 2026 - Present)\n- Global Client Data & Insight Intern @ Balenciaga (Jan 2025 - Jun 2025)\n- Intern @ Caitong Securities Ltd (Sep 2022 - Oct 2022)\n- Assistant Analyst @ Baker Tilly China (Mar 2022 - May 2022)\n\nEducation:\n- emlyon business school (2023 - 2025)\n- Computer Science, McGill University (May 2024 - Jun 2024)\n\nCertifications:\n- AWS Certified Machine Learning - Specialty (Jul 2024)\n- AWS Certified Cloud Practitioner (Mar 2024)\n\nTop Skills:\nStatistical Data Analysis, Data Management, Market Research, Python, Power BI, SQL, PostgreSQL, Tableau."
    ],
    [
        'first_name' => 'William Data',
        'last_name' => 'Denholm ウィリアム デンホルム',
        'headline' => 'Radiant Japan / SHJ Founder - THE Award winning Data Science, AI, Robotics recruitment company - Japan Market Entry Specialist',
        'org_name' => 'Radiant Japan',
        'location' => 'Tokyo, Tokyo, Japan',
        'linkedin' => 'https://www.linkedin.com/in/william-data-denholm%E3%80%80%E3%82%A6%E3%82%A3%E3%83%AA%E3%82%A2%E3%83%A0-%E3%83%87%E3%83%B3%E3%83%9B%E3%83%AB%E3%83%A0-918900106',
        'amount' => 12500.00,
        'stage_id' => $leadStageQual->id,
        'title' => 'William Data Denholm - Radiant Japan - Founder & Japan Market Entry Specialist',
        'summary' => 'Founder of Radiant Japan / SHJ, Tokyo-based Data Science, AI, and Robotics recruitment specialist. 20+ years global recruitment & Japan market entry expertise.',
        'notes' => "LinkedIn Profile: https://www.linkedin.com/in/william-data-denholm\nHeadline: Radiant Japan / SHJ Founder - THE Award winning Data Science, AI, Robotics recruitment company\nConnections: 22,022 | Followers: 21,503\nLocation: Tokyo, Tokyo, Japan\n\nExperience:\n- Founder @ Radiant Japan / SHJ (Jan 2016 - Present)\n- Business Development Head @ Globesoft Services Pte. Ltd. (Nov 2014 - Oct 2015)\n- Team Leader @ Empiric Solutions (Feb 2013 - Oct 2014)\n- Head of Contract Recruitment @ Argyll Scott Hong Kong (Oct 2010 - Dec 2012)\n- MD & Founder @ Transparent Consultancy (2006 - 2010)\n\nEducation:\n- Business & Finance, University of the West of England (1995 - 1998)\n- St Albans School (1988 - 1995)\n\nCertifications:\n- Licensed Singapore Recruiter (Ministry of Manpower)\n- Japanese Registered Responsible Recruitment Representative\n\nTop Skills:\nIT Recruitment, Business Development, Talent Acquisition, Data Science, AI, Executive Search, Japan Market Entry."
    ],
    [
        'first_name' => 'Jeanne',
        'last_name' => 'Carey, LSSGB, LCS 1C, PMP, CSM',
        'headline' => 'Enterprise Process & Transformation Leader | SAP ERP • PMP • Prosci • Lean Six Sigma | 25+ Years, Fortune 100 to Growth-Stage',
        'org_name' => 'Keystone Thinking',
        'location' => 'Marshfield, Massachusetts, United States',
        'linkedin' => 'https://www.linkedin.com/in/jeannegcarey',
        'amount' => 15000.00,
        'stage_id' => $leadStageQual->id,
        'title' => 'Jeanne Carey - Keystone Thinking - Enterprise Process & Transformation Leader',
        'summary' => 'Founder and Principal Consultant at Keystone Thinking. 25+ years enterprise process transformation leader across SAP ERP, PMP, Prosci, Lean Six Sigma, and corporate governance.',
        'notes' => "LinkedIn Profile: https://www.linkedin.com/in/jeannegcarey\nHeadline: Enterprise Process & Transformation Leader | SAP ERP • PMP • Prosci • Lean Six Sigma\nConnections: 1,157 | Followers: 1,269\nLocation: Marshfield, Massachusetts, United States\n\nExperience:\n- Founder / Principal Consultant @ Keystone Thinking (Jun 2024 - Present)\n- Home Energy Rater @ Home Energy Raters (Jul 2024 - May 2025)\n- Quality Manager @ Gainwell Technologies (Mar 2021 - Jul 2022)\n- Program Manager @ Brown University Health / Lifespan (Oct 2018 - Oct 2019)\n- Principal Process Manager @ National Grid (Jan 2018 - Sep 2018)\n- Director @ Electronic Data Systems / EDS (Mar 1995 - Apr 2008)\n- Assistant Director @ Cornell University (Apr 1990 - Jan 1995)\n\nEducation:\n- B.S. Biology & Secondary Education, SUNY Oswego\n- A.A.S. Alternative Energy Engineering, Lansing Community College\n\nCertifications:\n- Prosci® Certified Change Practitioner (Apr 2026)\n- Project Management Professional (PMP)® (Sep 2005 - Sep 2027)\n- LCS 1C Green/Black Belt (Mar 2018)\n- Certified Scrum Master (Aug 2017)\n- Lean Six Sigma Green Belt (Aug 2017)\n\nTop Skills:\nEnterprise Process Improvement, SAP Implementation, Large Scale Change Management, LEAN Management & Continuous Improvement, Corporate Governance."
    ]
];

foreach ($leadsPayload as $lp) {
    // 1. Organization
    $org = Organization::firstOrCreate(
        ['name' => $lp['org_name']],
        [
            'external_id' => Str::uuid()->toString(),
            'user_owner_id' => $userId,
        ]
    );

    // 2. Person
    $person = Person::create([
        'external_id' => Str::uuid()->toString(),
        'first_name' => $lp['first_name'],
        'last_name' => $lp['last_name'],
        'job_title' => $lp['headline'],
        'organization_id' => $org->id,
        'user_owner_id' => $userId,
    ]);

    // 3. Contact link
    Contact::firstOrCreate([
        'organization_id' => $org->id,
        'person_id' => $person->id,
    ]);

    // 4. Address
    if ($addressType) {
        Address::create([
            'external_id' => Str::uuid()->toString(),
            'address_type_id' => $addressType->id,
            'address' => $lp['location'],
            'primary' => true,
            'addressable_type' => Person::class,
            'addressable_id' => $person->id,
        ]);
    }

    // 5. Lead
    $num = $leadIdx++;
    $lead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'lead_id' => 'TFA-L' . $num,
        'prefix' => 'TFA-L',
        'number' => $num,
        'title' => $lp['title'],
        'description' => $lp['summary'],
        'amount' => $lp['amount'],
        'currency' => 'USD',
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'lead_source_id' => $leadSource->id,
        'pipeline_id' => $leadPipeline->id,
        'pipeline_stage_id' => $lp['stage_id'],
        'user_created_id' => $userId,
        'user_owner_id' => $userId,
        'user_assigned_id' => $userId,
    ]);

    if ($labelQuant) {
        $lead->labels()->attach($labelQuant->id);
    }

    // 6. Detailed Background Note
    Note::create([
        'external_id' => Str::uuid()->toString(),
        'content' => $lp['notes'],
        'noteable_type' => Lead::class,
        'noteable_id' => $lead->id,
        'user_created_id' => $userId,
        'user_updated_id' => $userId,
    ]);

    echo "Created Lead TFA-L{$num}: {$lp['title']}\n";
}

echo "All 4 leads successfully added into database!\n";
