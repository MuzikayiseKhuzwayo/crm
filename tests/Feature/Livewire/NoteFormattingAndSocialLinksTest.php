<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Services\LeadService;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('renders formatted note content with line breaks', function () {
    $user = User::create(['name' => 'Note Author', 'email' => 'author@example.com']);
    $this->actingAs($user);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'LinkedIn Outbound Prospect',
    ]);

    $note = Note::create([
        'external_id' => Uuid::uuid4()->toString(),
        'content' => "First line of discussion.\nSecond line of follow up.\n• Item 1\n• Item 2",
        'created_by' => $user->id,
    ]);

    $lead->notes()->save($note);

    Livewire::test(LeadShow::class, ['lead' => $lead])
        ->assertSeeHtml('First line of discussion.<br');
});

it('stores and updates linkedin, twitter, and website social links on leads', function () {
    $user = User::create(['name' => 'Lead Owner', 'email' => 'owner@example.com']);
    $this->actingAs($user);

    $request = (object) [
        'title' => 'LinkedIn Enterprise Deal',
        'description' => 'Targeting CTO on LinkedIn',
        'amount' => 5000,
        'currency' => 'USD',
        'user_owner_id' => $user->id,
        'linkedin' => 'https://linkedin.com/in/john-doe',
        'twitter' => '@johndoe',
        'website' => 'https://johndoe.com',
    ];

    $leadService = app(LeadService::class);
    $lead = $leadService->create($request);

    expect($lead->linkedin)->toBe('https://linkedin.com/in/john-doe')
        ->and($lead->twitter)->toBe('@johndoe')
        ->and($lead->website)->toBe('https://johndoe.com');
});
