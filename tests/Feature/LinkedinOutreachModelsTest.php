<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachEvent;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;
use VentureDrake\LaravelCrm\Models\Person;

test('linkedin outreach migration runs cleanly down and up with configured table prefix', function () {
    $migration = require dirname(__DIR__, 2).'/database/updates/2026_11_25_000001_create_laravel_crm_linkedin_outreach_tables.php';
    $prefix = config('laravel-crm.db_table_prefix');

    $migration->down();

    expect(Schema::hasTable($prefix.'linkedin_templates'))->toBeFalse()
        ->and(Schema::hasTable($prefix.'linkedin_campaigns'))->toBeFalse()
        ->and(Schema::hasTable($prefix.'linkedin_campaign_recipients'))->toBeFalse()
        ->and(Schema::hasTable($prefix.'linkedin_outreach_events'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable($prefix.'linkedin_templates'))->toBeTrue()
        ->and(Schema::hasTable($prefix.'linkedin_campaigns'))->toBeTrue()
        ->and(Schema::hasTable($prefix.'linkedin_campaign_recipients'))->toBeTrue()
        ->and(Schema::hasTable($prefix.'linkedin_outreach_events'))->toBeTrue();

    // Idempotent second run
    $migration->up();

    expect(Schema::hasTable($prefix.'linkedin_campaigns'))->toBeTrue();
});

test('linkedin template generates uuid, supports soft deletes, and renders placeholders', function () {
    $user = $this->actingAsUser();

    $template = LinkedinTemplate::create([
        'name' => 'Executive Connect Note',
        'type' => LinkedinTemplate::TYPE_CONNECT_NOTE,
        'body' => 'Hi {{first_name}} {{last_name}}, {{personalized_opener}} Saw your work as {{title}} at {{company}}.',
        'user_created_id' => $user->id,
        'user_updated_id' => $user->id,
    ]);

    expect($template->getTable())->toBe('crm_linkedin_templates')
        ->and($template->external_id)->not->toBeEmpty()
        ->and($template->createdByUser->id)->toBe($user->id)
        ->and($template->updatedByUser->id)->toBe($user->id);

    $rendered = $template->render([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'company' => 'Acme Corp',
        'title' => 'VP Engineering',
        'personalized_opener' => 'Loved your recent post on scaling.',
    ]);

    expect($rendered)->toBe('Hi Jane Doe, Loved your recent post on scaling. Saw your work as VP Engineering at Acme Corp.');

    $template->delete();

    expect(LinkedinTemplate::find($template->id))->toBeNull()
        ->and(LinkedinTemplate::withTrashed()->find($template->id))->not->toBeNull();
});

test('linkedin campaign applies safety defaults, casts json/timestamps, and links relationships', function () {
    $user = $this->actingAsUser();

    $connectTemplate = LinkedinTemplate::create([
        'name' => 'Connect Step',
        'type' => LinkedinTemplate::TYPE_CONNECT_NOTE,
        'body' => 'Hi {{first_name}}, let us connect.',
    ]);

    $dmTemplate = LinkedinTemplate::create([
        'name' => 'DM Step',
        'type' => LinkedinTemplate::TYPE_DM,
        'body' => 'Thanks for connecting {{first_name}}!',
    ]);

    $steps = [
        ['step' => 1, 'action' => 'connect', 'template_id' => $connectTemplate->id, 'delay_hours' => 0],
        ['step' => 2, 'action' => 'dm', 'template_id' => $dmTemplate->id, 'delay_hours' => 24],
    ];

    $campaign = LinkedinCampaign::create([
        'name' => 'Q4 Outbound Founders',
        'sequence_steps' => $steps,
        'user_owner_id' => $user->id,
        'user_created_id' => $user->id,
    ]);

    $fresh = $campaign->fresh();

    expect($fresh->getTable())->toBe('crm_linkedin_campaigns')
        ->and($fresh->external_id)->not->toBeEmpty()
        ->and($fresh->status)->toBe(LinkedinCampaign::STATUS_DRAFT)
        ->and($fresh->sequence_steps)->toBe($steps)
        ->and($fresh->daily_connect_limit)->toBe(15)
        ->and($fresh->daily_dm_limit)->toBe(30)
        ->and($fresh->weekly_connect_limit)->toBe(80)
        ->and($fresh->min_delay_seconds)->toBe(180)
        ->and($fresh->max_delay_seconds)->toBe(420)
        ->and($fresh->active_days)->toBe([1, 2, 3, 4, 5])
        ->and($fresh->active_start_hour)->toBe(8)
        ->and($fresh->active_end_hour)->toBe(18)
        ->and($fresh->timezone)->toBe('Africa/Johannesburg')
        ->and($fresh->total_recipients)->toBe(0)
        ->and($fresh->connects_sent_count)->toBe(0)
        ->and($fresh->accepted_count)->toBe(0)
        ->and($fresh->dms_sent_count)->toBe(0)
        ->and($fresh->replied_count)->toBe(0)
        ->and($fresh->failed_count)->toBe(0)
        ->and($fresh->ownerUser->id)->toBe($user->id)
        ->and($fresh->createdByUser->id)->toBe($user->id);

    $lead = Lead::create(['title' => 'Acme Corp - VP Eng']);
    $person = Person::create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $recipient = LinkedinCampaignRecipient::create([
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $lead->id,
        'person_id' => $person->id,
        'linkedin_url' => 'https://www.linkedin.com/in/Jane-Doe-123/?utm_source=share',
        'personalized_opener' => 'Great talk at SaaStr!',
    ]);

    $event = LinkedinOutreachEvent::create([
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_campaign_recipient_id' => $recipient->id,
        'action' => LinkedinOutreachEvent::ACTION_CONNECT_SENT,
        'step' => 1,
        'payload' => ['message' => 'Hi Jane, let us connect.'],
        'occurred_at' => now(),
    ]);

    expect($recipient->fresh()->normalized_linkedin_slug)->toBe('jane-doe-123')
        ->and($recipient->campaign->id)->toBe($campaign->id)
        ->and($recipient->lead->id)->toBe($lead->id)
        ->and($recipient->person->id)->toBe($person->id)
        ->and($recipient->events)->toHaveCount(1)
        ->and($campaign->recipients)->toHaveCount(1)
        ->and($campaign->events)->toHaveCount(1)
        ->and($event->campaign->id)->toBe($campaign->id)
        ->and($event->recipient->id)->toBe($recipient->id)
        ->and($event->payload)->toBe(['message' => 'Hi Jane, let us connect.']);
});

test('normalizeLinkedinUrl extracts canonical slug and rejects non-profile urls', function (string $input, ?string $expected) {
    expect(LinkedinCampaignRecipient::normalizeLinkedinUrl($input))->toBe($expected);
})->with([
    'standard https url with trailing slash' => ['https://www.linkedin.com/in/john-doe-123/', 'john-doe-123'],
    'uppercase slug with query params' => ['http://linkedin.com/in/John-Doe-123?trk=public_profile&utm_medium=web', 'john-doe-123'],
    'country subdomain with nested sub-path' => ['https://za.linkedin.com/in/jane-smith-456/details/experience/', 'jane-smith-456'],
    'missing scheme with fragment' => ['www.linkedin.com/in/alex-jones#about', 'alex-jones'],
    'bare /in/ path' => ['/in/sam-taylor-99/', 'sam-taylor-99'],
    'url encoded characters' => ['https://www.linkedin.com/in/jos%C3%A9-garcia/', 'josé-garcia'],
    'company page rejected' => ['https://www.linkedin.com/company/acme-corp/', null],
    'non-linkedin host rejected' => ['https://example.com/in/john-doe', null],
    'lookalike domain rejected' => ['https://notlinkedin.com/in/john-doe', null],
    'empty profile path rejected' => ['https://www.linkedin.com/in/', null],
    'empty string rejected' => ['   ', null],
]);

test('updating recipient linkedin_url refreshes normalized_linkedin_slug automatically', function () {
    $campaign = LinkedinCampaign::create(['name' => 'Slug Update Campaign']);

    $recipient = LinkedinCampaignRecipient::create([
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/old-slug/',
    ]);

    expect($recipient->normalized_linkedin_slug)->toBe('old-slug');

    $recipient->update([
        'linkedin_url' => 'https://www.linkedin.com/in/New-Slug-42/?trk=abc',
    ]);

    expect($recipient->fresh()->normalized_linkedin_slug)->toBe('new-slug-42');
});

test('recipient scopes filter dueForAction, expiredLeases, and activeInSequence accurately', function () {
    $now = Carbon::parse('2026-11-25 10:00:00');
    Carbon::setTestNow($now);

    try {
        $campaign = LinkedinCampaign::create(['name' => 'Scope Test Campaign']);

        $queuedImmediate = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/queued-immediate',
            'status' => LinkedinCampaignRecipient::STATUS_QUEUED,
            'next_action_at' => null,
        ]);

        $connectedDue = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/connected-due',
            'status' => LinkedinCampaignRecipient::STATUS_CONNECTED,
            'next_action_at' => $now->copy()->subMinutes(5),
        ]);

        $inSequenceFuture = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/in-sequence-future',
            'status' => LinkedinCampaignRecipient::STATUS_IN_SEQUENCE,
            'next_action_at' => $now->copy()->addHours(2),
        ]);

        $connectSentWaiting = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/connect-sent',
            'status' => LinkedinCampaignRecipient::STATUS_CONNECT_SENT,
            'next_action_at' => $now->copy()->subHour(),
        ]);

        $leasedActive = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/leased-active',
            'status' => LinkedinCampaignRecipient::STATUS_LEASED,
            'lease_token' => 'tok-active',
            'leased_at' => $now->copy()->subMinute(),
            'lease_expires_at' => $now->copy()->addMinutes(5),
            'worker_id' => 'worker-1',
        ]);

        $leasedExpired = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/leased-expired',
            'status' => LinkedinCampaignRecipient::STATUS_LEASED,
            'lease_token' => 'tok-expired',
            'leased_at' => $now->copy()->subMinutes(15),
            'lease_expires_at' => $now->copy()->subMinutes(5),
            'worker_id' => 'worker-2',
        ]);

        $replied = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/replied-user',
            'status' => LinkedinCampaignRecipient::STATUS_REPLIED,
            'replied_at' => $now->copy()->subMinutes(10),
        ]);

        $completed = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/completed-user',
            'status' => LinkedinCampaignRecipient::STATUS_COMPLETED,
            'completed_at' => $now->copy()->subMinutes(10),
        ]);

        $failed = LinkedinCampaignRecipient::create([
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/failed-user',
            'status' => LinkedinCampaignRecipient::STATUS_FAILED,
            'last_error_code' => 'PROFILE_NOT_FOUND',
        ]);

        $dueIds = LinkedinCampaignRecipient::dueForAction()->pluck('id')->all();
        expect($dueIds)->toEqualCanonicalizing([
            $queuedImmediate->id,
            $connectedDue->id,
        ]);

        $expiredIds = LinkedinCampaignRecipient::expiredLeases()->pluck('id')->all();
        expect($expiredIds)->toBe([$leasedExpired->id]);

        $activeIds = LinkedinCampaignRecipient::activeInSequence()->pluck('id')->all();
        expect($activeIds)->toEqualCanonicalizing([
            $queuedImmediate->id,
            $connectedDue->id,
            $inSequenceFuture->id,
            $connectSentWaiting->id,
            $leasedActive->id,
            $leasedExpired->id,
        ]);

        expect($activeIds)
            ->not->toContain($replied->id)
            ->not->toContain($completed->id)
            ->not->toContain($failed->id);
    } finally {
        Carbon::setTestNow();
    }
});

test('recipient progresses through outreach state machine and records events', function () {
    $campaign = LinkedinCampaign::create([
        'name' => 'Lifecycle Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
    ]);

    $recipient = LinkedinCampaignRecipient::create([
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/lifecycle-prospect/',
    ]);

    expect($recipient->status)->toBe(LinkedinCampaignRecipient::STATUS_QUEUED)
        ->and($recipient->current_step)->toBe(0);

    // Lease for step 1
    $recipient->update([
        'status' => LinkedinCampaignRecipient::STATUS_LEASED,
        'lease_token' => 'lease-1',
        'leased_at' => now(),
        'lease_expires_at' => now()->addMinutes(5),
        'worker_id' => 'worker-alpha',
        'attempt_count' => 1,
    ]);

    expect($recipient->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_LEASED);

    // Connect sent
    $recipient->update([
        'status' => LinkedinCampaignRecipient::STATUS_CONNECT_SENT,
        'current_step' => 1,
        'connect_sent_at' => now(),
        'lease_token' => null,
        'leased_at' => null,
        'lease_expires_at' => null,
    ]);

    LinkedinOutreachEvent::create([
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_campaign_recipient_id' => $recipient->id,
        'action' => LinkedinOutreachEvent::ACTION_CONNECT_SENT,
        'step' => 1,
        'payload' => ['note' => 'Hi!'],
        'occurred_at' => now(),
    ]);

    // Connected -> schedules step 2 DM
    $recipient->update([
        'status' => LinkedinCampaignRecipient::STATUS_CONNECTED,
        'connected_at' => now(),
        'next_action_at' => now()->addHours(24),
    ]);

    // In sequence -> DM sent -> Replied
    $recipient->update([
        'status' => LinkedinCampaignRecipient::STATUS_IN_SEQUENCE,
        'current_step' => 2,
        'last_dm_sent_at' => now(),
    ]);

    $recipient->update([
        'status' => LinkedinCampaignRecipient::STATUS_REPLIED,
        'replied_at' => now(),
    ]);

    LinkedinOutreachEvent::create([
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_campaign_recipient_id' => $recipient->id,
        'action' => LinkedinOutreachEvent::ACTION_REPLY_RECEIVED,
        'step' => 2,
        'payload' => ['snippet' => 'Sounds interesting, let us chat!'],
        'occurred_at' => now(),
    ]);

    // Campaign-level circuit breaker event (no recipient)
    $campaign->update([
        'status' => LinkedinCampaign::STATUS_CIRCUIT_BROKEN,
        'circuit_broken_at' => now(),
        'circuit_broken_reason' => 'CAPTCHA_DETECTED',
    ]);

    LinkedinOutreachEvent::create([
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_campaign_recipient_id' => null,
        'action' => LinkedinOutreachEvent::ACTION_CIRCUIT_BREAKER_TRIPPED,
        'step' => null,
        'payload' => ['reason' => 'CAPTCHA_DETECTED'],
        'occurred_at' => now(),
    ]);

    expect($recipient->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_REPLIED)
        ->and($recipient->fresh()->replied_at)->not->toBeNull()
        ->and($recipient->events()->count())->toBe(2)
        ->and($campaign->fresh()->isCircuitBroken())->toBeTrue()
        ->and($campaign->events()->count())->toBe(3);
});
