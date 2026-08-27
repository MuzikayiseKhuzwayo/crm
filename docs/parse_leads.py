import json
import os

json_path = 'dataset_linkedin-profile-search_2026-08-27_23-44-53-095.json'
with open(json_path, 'r', encoding='utf-8') as f:
    profiles = json.load(f)

print(f"Loaded {len(profiles)} profiles.")

# The first 4 profiles were Alex Lokhov, Muyun Chu, William Data Denholm, Jeanne Carey.
# We will process all profiles, using first_name + last_name check to avoid duplicates.

php_script = """<?php

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
$app->register(\\VentureDrake\\LaravelCrm\\LaravelCrmServiceProvider::class);
$kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class);
$kernel->bootstrap();

config(['laravel-crm.db_table_prefix' => 'crm_']);

use Illuminate\\Support\\Str;
use VentureDrake\\LaravelCrm\\Models\\Organization;
use VentureDrake\\LaravelCrm\\Models\\Person;
use VentureDrake\\LaravelCrm\\Models\\Address;
use VentureDrake\\LaravelCrm\\Models\\AddressType;
use VentureDrake\\LaravelCrm\\Models\\Lead;
use VentureDrake\\LaravelCrm\\Models\\LeadSource;
use VentureDrake\\LaravelCrm\\Models\\Label;
use VentureDrake\\LaravelCrm\\Models\\Pipeline;
use VentureDrake\\LaravelCrm\\Models\\PipelineStage;
use VentureDrake\\LaravelCrm\\Models\\Note;

$userModel = config('auth.providers.users.model') ?? \\App\\Models\\User::class;
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

$maxLeadNum = Lead::max('number') ?? 1000;
$leadIdx = $maxLeadNum + 1;

$leadsPayload = """

leads_list = []
for idx, p in enumerate(profiles):
    first_name = (p.get('firstName') or '').strip()
    last_name = (p.get('lastName') or '').strip()
    headline = (p.get('headline') or '').strip()
    linkedin = (p.get('linkedinUrl') or '').strip()
    
    loc_data = p.get('location') or {}
    location = loc_data.get('linkedinText') or (loc_data.get('parsed') or {}).get('text') or 'Global'
    
    cur_pos_list = p.get('currentPosition') or []
    if cur_pos_list:
        company_name = (cur_pos_list[0].get('companyName') or 'Independent / Stealth').strip()
        job_pos = (cur_pos_list[0].get('position') or headline or 'Executive').strip()
    else:
        company_name = 'Independent / Stealth'
        job_pos = headline or 'Executive'
        
    lead_title = f"{first_name} {last_name} - {company_name} - {job_pos[:50]}".strip()
    
    about = (p.get('about') or '').strip()
    summary = about[:250] if about else f"{job_pos} at {company_name}. Located in {location}."
    
    notes_lines = [
        f"LinkedIn Profile: {linkedin}",
        f"Headline: {headline}",
        f"Connections: {p.get('connectionsCount', 0)} | Followers: {p.get('followerCount', 0)}",
        f"Location: {location}",
        ""
    ]
    
    if about:
        notes_lines.append("About:")
        notes_lines.append(about)
        notes_lines.append("")
        
    exp_list = p.get('experience') or []
    if exp_list:
        notes_lines.append("Experience:")
        for exp in exp_list[:6]:
            pos = exp.get('position') or ''
            comp = exp.get('companyName') or ''
            start = (exp.get('startDate') or {}).get('text') or ''
            end = (exp.get('endDate') or {}).get('text') or ''
            time_str = f" ({start} - {end})" if start else ""
            notes_lines.append(f"- {pos} @ {comp}{time_str}")
        notes_lines.append("")
        
    edu_list = p.get('education') or []
    if edu_list:
        notes_lines.append("Education:")
        for edu in edu_list[:4]:
            school = edu.get('schoolName') or ''
            degree = edu.get('degree') or ''
            field = edu.get('fieldOfStudy') or ''
            deg_str = f" ({degree} - {field})" if degree or field else ""
            notes_lines.append(f"- {school}{deg_str}")
        notes_lines.append("")
        
    cert_list = p.get('certifications') or []
    if cert_list:
        notes_lines.append("Certifications:")
        for cert in cert_list[:6]:
            ctitle = cert.get('title') or ''
            by = cert.get('issuedBy') or ''
            notes_lines.append(f"- {ctitle} ({by})")
        notes_lines.append("")
        
    skills_list = p.get('skills') or []
    if skills_list:
        sk_names = [s.get('name') for s in skills_list if s.get('name')]
        if sk_names:
            notes_lines.append(f"Top Skills:\\n{', '.join(sk_names[:15])}")
            
    notes_text = "\\n".join(notes_lines)
    
    # Estimate deal amount ($3,500 to $12,500) based on title/company
    amount = 6500.00
    if any(k in company_name.lower() or k in headline.lower() for k in ['trading', 'hedge', 'capital', 'director', 'founder', 'vp', 'head']):
        amount = 12500.00
    elif any(k in job_pos.lower() for k in ['intern', 'assistant', 'analyst']):
        amount = 3500.00

    leads_list.append({
        'first_name': first_name,
        'last_name': last_name,
        'headline': headline,
        'org_name': company_name,
        'location': location,
        'linkedin': linkedin,
        'amount': amount,
        'title': lead_title,
        'summary': summary,
        'notes': notes_text
    })

php_script += json.dumps(leads_list, indent=4, ensure_ascii=False) + ";\n\n"

php_script += """
$insertedCount = 0;
foreach ($leadsPayload as $lp) {
    // Check if person already exists by first and last name
    $existingPerson = Person::where('first_name', $lp['first_name'])
        ->where('last_name', $lp['last_name'])
        ->first();
        
    if ($existingPerson) {
        echo "Skipping existing lead: {$lp['first_name']} {$lp['last_name']}\\n";
        continue;
    }

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

    // 3. Address
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

    // 4. Lead
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
        'content' => $lp['notes'],
        'noteable_type' => Lead::class,
        'noteable_id' => $lead->id,
        'user_created_id' => $userId,
        'user_updated_id' => $userId,
    ]);

    $insertedCount++;
    echo "Created Lead TFA-L{$num}: {$lp['title']}\\n";
}

echo "Done! Successfully inserted {$insertedCount} new leads into the database.\\n";
"""

with open('docs/import_all_leads.php', 'w', encoding='utf-8') as out_f:
    out_f.write(php_script)

print("Generated docs/import_all_leads.php successfully.")
