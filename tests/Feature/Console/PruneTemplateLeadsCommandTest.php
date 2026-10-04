<?php

use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('previews template leads deletion with --dry-run without deleting', function () {
    $user = User::create(['name' => 'Prune Officer', 'email' => 'prune@example.com']);
    $this->actingAs($user);

    $templateLead = Lead::create([
        'title' => 'Enquiry about Enterprise CRM',
        'linkedin' => null,
    ]);

    $realLead = Lead::create([
        'title' => 'Real LinkedIn Lead',
        'linkedin' => 'https://www.linkedin.com/in/real-prospect',
    ]);

    $this->artisan('laravelcrm:prune-template-leads', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN: No records were deleted.')
        ->assertExitCode(0);

    expect(Lead::find($templateLead->id))->not->toBeNull()
        ->and(Lead::find($realLead->id))->not->toBeNull();
});

it('prunes template leads and unlinks related deals while keeping authentic leads', function () {
    $user = User::create(['name' => 'Prune Officer 2', 'email' => 'prune2@example.com']);
    $this->actingAs($user);

    $templateLead = Lead::create([
        'title' => 'Potential Cloud Hosting deal',
        'linkedin' => null,
    ]);

    $realLead = Lead::create([
        'title' => 'Real Lead with LinkedIn',
        'linkedin' => 'https://www.linkedin.com/in/prospect-ceo',
    ]);

    $deal = Deal::create([
        'title' => 'Test Deal Attached to Template Lead',
        'lead_id' => $templateLead->id,
    ]);

    $quote = Quote::create([
        'title' => 'Test Quote Attached to Template Lead',
        'lead_id' => $templateLead->id,
    ]);

    $order = Order::create([
        'lead_id' => $templateLead->id,
    ]);

    $this->artisan('laravelcrm:prune-template-leads', ['--force' => true])
        ->expectsOutputToContain('Successfully deleted')
        ->assertExitCode(0);

    // Template lead is deleted
    expect(Lead::find($templateLead->id))->toBeNull()
        // Authentic lead remains intact
        ->and(Lead::find($realLead->id))->not->toBeNull()
        // Deal, Quote, Order have lead_id detached safely
        ->and($deal->fresh()->lead_id)->toBeNull()
        ->and($quote->fresh()->lead_id)->toBeNull()
        ->and($order->fresh()->lead_id)->toBeNull();
});
