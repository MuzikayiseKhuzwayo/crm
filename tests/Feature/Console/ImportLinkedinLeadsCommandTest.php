<?php

use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('imports linkedin leads from json dataset into crm entities', function () {
    $user = User::create(['name' => 'Import Operator', 'email' => 'operator@example.com']);
    $this->actingAs($user);

    $dataset = [
        [
            'id' => 'profile-1',
            'firstName' => 'Samantha',
            'lastName' => 'Vance',
            'headline' => 'VP of Engineering at TechGlobal | Distributed Systems',
            'linkedinUrl' => 'https://www.linkedin.com/in/samantha-vance-test',
            'about' => 'Seasoned technology leader with 15+ years of distributed architecture experience.',
            'connectionsCount' => 4500,
            'followerCount' => 5200,
            'location' => [
                'linkedinText' => 'San Francisco Bay Area, CA, US',
                'countryCode' => 'US',
                'parsed' => [
                    'city' => 'San Francisco',
                    'state' => 'California',
                    'country' => 'United States',
                ],
            ],
            'currentPosition' => [
                [
                    'companyName' => 'TechGlobal Inc',
                    'position' => 'VP of Engineering',
                    'companyLinkedinUrl' => 'https://www.linkedin.com/company/techglobal-test',
                ],
            ],
        ],
        [
            'id' => 'profile-2',
            'firstName' => 'Marcus',
            'lastName' => 'Aurelius',
            'headline' => 'Founder & Managing Director',
            'linkedinUrl' => 'https://www.linkedin.com/in/marcus-aurelius-test',
            'about' => 'Stoic leadership and modern enterprise consulting.',
            'connectionsCount' => 12000,
            'followerCount' => 15000,
            'location' => [
                'linkedinText' => 'Rome, Italy',
                'countryCode' => 'IT',
                'parsed' => [
                    'city' => 'Rome',
                    'state' => 'Lazio',
                    'country' => 'Italy',
                ],
            ],
            'currentPosition' => [
                [
                    'companyName' => 'TechGlobal Inc', // Same company to test org reuse
                    'position' => 'Strategic Advisor',
                    'companyLinkedinUrl' => 'https://www.linkedin.com/company/techglobal-test',
                ],
            ],
        ],
    ];

    $tempFile = tempnam(sys_get_temp_dir(), 'linkedin_leads_test_').'.json';
    file_put_contents($tempFile, json_encode($dataset));

    // Test Dry-run
    $this->artisan('laravelcrm:import-linkedin-leads', [
        'file' => $tempFile,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(Lead::where('linkedin', 'https://www.linkedin.com/in/samantha-vance-test')->exists())->toBeFalse();

    // Actual Import
    $this->artisan('laravelcrm:import-linkedin-leads', [
        'file' => $tempFile,
        '--user' => $user->id,
    ])->assertExitCode(0);

    // Verify Organization was created and reused (1 org for both leads)
    $orgs = Organization::where('name', 'TechGlobal Inc')->get();
    expect($orgs)->toHaveCount(1);
    $org = $orgs->first();
    expect($org->linkedin)->toBe('https://www.linkedin.com/company/techglobal-test');

    // Verify Samantha Lead & Person
    $samLead = Lead::where('linkedin', 'https://www.linkedin.com/in/samantha-vance-test')->first();
    expect($samLead)->not->toBeNull()
        ->and($samLead->title)->toBe('TechGlobal Inc - VP of Engineering')
        ->and($samLead->organization_id)->toBe($org->id)
        ->and($samLead->person)->not->toBeNull()
        ->and($samLead->person->first_name)->toBe('Samantha')
        ->and($samLead->person->last_name)->toBe('Vance')
        ->and($samLead->person->organization_id)->toBe($org->id)
        ->and($samLead->person->linkedin)->toBe('https://www.linkedin.com/in/samantha-vance-test');

    // Verify Address on Person
    $address = $samLead->person->addresses->first();
    expect($address)->not->toBeNull()
        ->and($address->city)->toBe('San Francisco')
        ->and($address->country)->toBe('United States');

    // Verify Marcus Lead & Person
    $marcusLead = Lead::where('linkedin', 'https://www.linkedin.com/in/marcus-aurelius-test')->first();
    expect($marcusLead)->not->toBeNull()
        ->and($marcusLead->title)->toBe('TechGlobal Inc - Strategic Advisor')
        ->and($marcusLead->organization_id)->toBe($org->id)
        ->and($marcusLead->person->first_name)->toBe('Marcus');

    // Test Deduplication (running again should skip existing)
    $this->artisan('laravelcrm:import-linkedin-leads', [
        'file' => $tempFile,
        '--user' => $user->id,
    ])
        ->expectsOutputToContain('Skipped (Existing / Empty)')
        ->assertExitCode(0);

    expect(Lead::where('linkedin', 'https://www.linkedin.com/in/samantha-vance-test')->count())->toBe(1);

    @unlink($tempFile);
});
