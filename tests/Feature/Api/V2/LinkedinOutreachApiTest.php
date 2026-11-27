<?php

use Carbon\Carbon;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadStatus;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachEvent;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

function linkedinApiUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'LinkedIn API User',
        'email' => 'linkedin-api-'.uniqid().'@example.com',
        'password' => bcrypt('secret-password'),
        'crm_access' => true,
    ], $attributes));
}

function linkedinApiHeaders(User $user, ?int $teamId = null, array $extra = []): array
{
    $token = $user->createToken('chrome-extension')->plainTextToken;

    $headers = [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];

    if ($teamId !== null) {
        $headers['X-Team-ID'] = (string) $teamId;
    }

    return array_merge($headers, $extra);
}

function makeActiveCampaign(array $overrides = []): LinkedinCampaign
{
    return LinkedinCampaign::create(array_merge([
        'name' => 'Founders Q2',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'daily_connect_limit' => 15,
        'daily_dm_limit' => 30,
        'weekly_connect_limit' => 80,
        'min_delay_seconds' => 0,
        'max_delay_seconds' => 0,
        'active_days' => [1, 2, 3, 4, 5, 6, 7],
        'active_start_hour' => 0,
        'active_end_hour' => 24,
        'timezone' => 'UTC',
        'sequence_steps' => [
            [
                'step' => 1,
                'action' => 'connect',
                'body' => 'Hi {{first_name}}, would love to connect!',
            ],
            [
                'step' => 2,
                'action' => 'dm',
                'delay_hours' => 24,
                'body' => 'Thanks for connecting {{first_name}}!',
            ],
        ],
    ], $overrides));
}

function makeDueRecipient(LinkedinCampaign $campaign, array $overrides = []): LinkedinCampaignRecipient
{
    $slug = $overrides['normalized_linkedin_slug'] ?? 'jane-doe-'.uniqid();

    return LinkedinCampaignRecipient::create(array_merge([
        'team_id' => $campaign->team_id,
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/'.$slug.'/',
        'normalized_linkedin_slug' => $slug,
        'status' => LinkedinCampaignRecipient::STATUS_QUEUED,
        'current_step' => 0,
        'next_action_at' => now()->subMinute(),
    ], $overrides));
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 10:00:00', 'UTC'));

    if (! LeadStatus::query()->where('id', 1)->exists()) {
        LeadStatus::query()->insert([
            'id' => 1,
            'name' => 'New',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
});

afterEach(function () {
    Carbon::setTestNow();
    config()->set('laravel-crm.teams', false);
    config()->set('laravel-crm.api.allow_chrome_extensions', true);
    config()->set('laravel-crm.api.allowed_extension_ids', []);
});

test('all linkedin outreach endpoints reject unauthenticated requests with 401', function () {
    $uuid = (string) Str::uuid();

    $this->getJson('/crm/api/v2/linkedin/status')->assertStatus(401);
    $this->postJson('/crm/api/v2/linkedin/tasks/claim', ['worker_id' => 'ext-1'])->assertStatus(401);
    $this->postJson("/crm/api/v2/linkedin/tasks/{$uuid}/complete", ['lease_token' => 'tok'])->assertStatus(401);
    $this->postJson("/crm/api/v2/linkedin/tasks/{$uuid}/fail", ['lease_token' => 'tok', 'error_code' => 'transient_dom_error'])->assertStatus(401);
    $this->postJson('/crm/api/v2/linkedin/inbox/sync', ['accepted_slugs' => [], 'replies' => []])->assertStatus(401);
    $this->postJson('/crm/api/v2/linkedin/circuit-breaker/reset')->assertStatus(401);
});

test('GET linkedin/status returns active campaigns, quotas, due count, and updates heartbeat', function () {
    $user = linkedinApiUser();
    $campaign = makeActiveCampaign(['total_recipients' => 2]);
    makeDueRecipient($campaign);
    makeDueRecipient($campaign, ['next_action_at' => now()->addHour()]);

    $response = $this->withHeaders(linkedinApiHeaders($user))
        ->getJson('/crm/api/v2/linkedin/status?worker_id=chrome-ext-1');

    $response->assertOk();
    $data = $response->json('data');

    expect($data['worker_id'])->toBe('chrome-ext-1');
    expect($data['heartbeat_at'])->not->toBeNull();
    expect($data['active_campaigns_count'])->toBe(1);
    expect($data['active_campaigns'][0]['id'])->toBe($campaign->external_id);
    expect($data['due_tasks_count'])->toBe(1);
    expect($data['remaining_today'])->toBe(['connect' => 15, 'dm' => 30]);
    expect($data['next_eligible_at'])->toBeNull();
    expect($data['circuit_broken'])->toBeFalse();
    expect($data['circuit_broken_reason'])->toBeNull();

    expect($campaign->fresh()->last_worker_heartbeat_at)->not->toBeNull();
});

test('POST linkedin/tasks/claim leases the next due task and returns meta when nothing is eligible', function () {
    $user = linkedinApiUser();
    $person = Person::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $lead = Lead::create([
        'title' => 'Ada Lead',
        'person_id' => $person->id,
        'linkedin' => 'https://www.linkedin.com/in/ada-lovelace/',
    ]);
    $campaign = makeActiveCampaign();
    $recipient = makeDueRecipient($campaign, [
        'lead_id' => $lead->id,
        'person_id' => $person->id,
        'linkedin_url' => 'https://www.linkedin.com/in/ada-lovelace/',
        'normalized_linkedin_slug' => 'ada-lovelace',
    ]);

    $claimResponse = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson('/crm/api/v2/linkedin/tasks/claim', [
            'worker_id' => 'ext-worker-1',
            'lease_seconds' => 180,
        ]);

    $claimResponse->assertOk();
    $task = $claimResponse->json('data');

    expect($task['recipient_external_id'])->toBe($recipient->external_id);
    expect($task['lease_token'])->toBeString()->not->toBeEmpty();
    expect($task['action'])->toBe('connect');
    expect($task['step'])->toBe(1);
    expect($task['linkedin_url'])->toBe('https://www.linkedin.com/in/ada-lovelace/');
    expect($task['message_body'])->toBe('Hi Ada, would love to connect!');
    expect($task['person_name'])->toBe('Ada Lovelace');

    $recipient->refresh();
    expect($recipient->status)->toBe(LinkedinCampaignRecipient::STATUS_LEASED);
    expect($recipient->worker_id)->toBe('ext-worker-1');

    // Second claim has nothing left due.
    $emptyResponse = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson('/crm/api/v2/linkedin/tasks/claim', [
            'worker_id' => 'ext-worker-1',
        ]);

    $emptyResponse->assertOk();
    expect($emptyResponse->json('data'))->toBeNull();
    expect($emptyResponse->json('meta.reason'))->toBe('no_due_tasks');
});

test('POST linkedin/tasks/claim validates worker_id and lease_seconds bounds', function () {
    $user = linkedinApiUser();

    $this->withHeaders(linkedinApiHeaders($user))
        ->postJson('/crm/api/v2/linkedin/tasks/claim', [
            'lease_seconds' => 10,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['worker_id', 'lease_seconds']);
});

test('POST linkedin/tasks/{recipient}/complete rejects invalid lease_token with 409 and completes valid lease', function () {
    $user = linkedinApiUser();
    $campaign = makeActiveCampaign();
    $recipient = makeDueRecipient($campaign);

    $claim = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson('/crm/api/v2/linkedin/tasks/claim', ['worker_id' => 'ext-1'])
        ->json('data');

    // Invalid lease token -> 409 Conflict
    $this->withHeaders(linkedinApiHeaders($user))
        ->postJson("/crm/api/v2/linkedin/tasks/{$recipient->external_id}/complete", [
            'lease_token' => 'wrong-token',
            'sent_text' => 'Hi there!',
        ])
        ->assertStatus(409);

    // Valid lease token -> 200 OK
    $complete = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson("/crm/api/v2/linkedin/tasks/{$recipient->external_id}/complete", [
            'lease_token' => $claim['lease_token'],
            'sent_text' => 'Hi there, connecting from CRM!',
            'metadata' => ['dom_version' => '2026.1'],
        ]);

    $complete->assertOk();
    expect($complete->json('data.id'))->toBe($recipient->external_id);
    expect($complete->json('data.status'))->toBe(LinkedinCampaignRecipient::STATUS_CONNECT_SENT);
    expect($complete->json('data.current_step'))->toBe(1);
    expect($complete->json('data.connect_sent_at'))->not->toBeNull();

    $event = LinkedinOutreachEvent::query()
        ->where('linkedin_campaign_recipient_id', $recipient->id)
        ->where('action', LinkedinOutreachEvent::ACTION_CONNECT_SENT)
        ->first();

    expect($event)->not->toBeNull();
    expect($event->payload['sent_text'])->toBe('Hi there, connecting from CRM!');
    expect($event->payload['dom_version'])->toBe('2026.1');
});

test('POST linkedin/tasks/{recipient}/fail trips circuit breaker on captcha_detected and reset restores campaign', function () {
    $user = linkedinApiUser();
    $campaign = makeActiveCampaign();
    $recipient = makeDueRecipient($campaign);

    $claim = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson('/crm/api/v2/linkedin/tasks/claim', ['worker_id' => 'ext-1'])
        ->json('data');

    // Reject unknown error_code
    $this->withHeaders(linkedinApiHeaders($user))
        ->postJson("/crm/api/v2/linkedin/tasks/{$recipient->external_id}/fail", [
            'lease_token' => $claim['lease_token'],
            'error_code' => 'made_up_error',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['error_code']);

    // Reject wrong lease_token
    $this->withHeaders(linkedinApiHeaders($user))
        ->postJson("/crm/api/v2/linkedin/tasks/{$recipient->external_id}/fail", [
            'lease_token' => 'wrong-token',
            'error_code' => 'captcha_detected',
        ])
        ->assertStatus(409);

    // Valid captcha_detected trips circuit breaker
    $failResponse = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson("/crm/api/v2/linkedin/tasks/{$recipient->external_id}/fail", [
            'lease_token' => $claim['lease_token'],
            'error_code' => 'captcha_detected',
            'error_message' => 'Arkose challenge appeared',
        ]);

    $failResponse->assertOk();
    expect($failResponse->json('data.circuit_broken'))->toBeTrue();
    expect($failResponse->json('data.circuit_broken_reason'))->toBe('captcha_detected');
    expect($failResponse->json('data.status'))->toBe(LinkedinCampaignRecipient::STATUS_QUEUED);

    expect($campaign->fresh()->status)->toBe(LinkedinCampaign::STATUS_CIRCUIT_BROKEN);

    // Status reflects circuit breaker
    $status = $this->withHeaders(linkedinApiHeaders($user))
        ->getJson('/crm/api/v2/linkedin/status')
        ->assertOk()
        ->json('data');
    expect($status['circuit_broken'])->toBeTrue();
    expect($status['circuit_broken_reason'])->toBe('captcha_detected');

    // Reset without resume -> paused
    $resetPaused = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson('/crm/api/v2/linkedin/circuit-breaker/reset');

    $resetPaused->assertOk();
    expect($resetPaused->json('data.reset_count'))->toBe(1);
    expect($resetPaused->json('data.status'))->toBe(LinkedinCampaign::STATUS_PAUSED);
    expect($campaign->fresh()->status)->toBe(LinkedinCampaign::STATUS_PAUSED);
    expect($campaign->fresh()->circuit_broken_reason)->toBeNull();

    // Trip again and reset with resume: true -> active
    $campaign->update([
        'status' => LinkedinCampaign::STATUS_CIRCUIT_BROKEN,
        'circuit_broken_at' => now(),
        'circuit_broken_reason' => 'rate_limited',
    ]);

    $resetActive = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson('/crm/api/v2/linkedin/circuit-breaker/reset', ['resume' => true]);

    $resetActive->assertOk();
    expect($resetActive->json('data.status'))->toBe(LinkedinCampaign::STATUS_ACTIVE);
    expect($campaign->fresh()->status)->toBe(LinkedinCampaign::STATUS_ACTIVE);
});

test('POST linkedin/inbox/sync updates accepted connections, replies, and lead relay_status', function () {
    $user = linkedinApiUser();
    $campaign = makeActiveCampaign();

    $acceptedRecipient = makeDueRecipient($campaign, [
        'normalized_linkedin_slug' => 'accepted-prospect',
        'linkedin_url' => 'https://www.linkedin.com/in/accepted-prospect/',
        'status' => LinkedinCampaignRecipient::STATUS_CONNECT_SENT,
        'current_step' => 1,
        'next_action_at' => null,
    ]);

    $repliedLead = Lead::create([
        'title' => 'Engaged Lead',
        'linkedin' => 'https://www.linkedin.com/in/replied-prospect/',
    ]);
    $repliedRecipient = makeDueRecipient($campaign, [
        'lead_id' => $repliedLead->id,
        'normalized_linkedin_slug' => 'replied-prospect',
        'linkedin_url' => 'https://www.linkedin.com/in/replied-prospect/',
        'status' => LinkedinCampaignRecipient::STATUS_IN_SEQUENCE,
        'current_step' => 2,
    ]);

    $response = $this->withHeaders(linkedinApiHeaders($user))
        ->postJson('/crm/api/v2/linkedin/inbox/sync', [
            'accepted_slugs' => ['accepted-prospect'],
            'replies' => [
                [
                    'slug' => 'replied-prospect',
                    'snippet' => 'Sounds interesting, let us chat Thursday!',
                    'replied_at' => '2026-06-10T09:45:00Z',
                ],
            ],
        ]);

    $response->assertOk();
    expect($response->json('data'))->toBe([
        'accepted' => 1,
        'replied' => 1,
        'outbound_synced' => 0,
        'updated_recipients' => 2,
    ]);

    expect($acceptedRecipient->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_CONNECTED);
    expect($repliedRecipient->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_REPLIED);
    expect($repliedLead->fresh()->relay_status)->toBe('engaged');
});

test('linkedin outreach endpoints enforce strict team scoping', function () {
    config()->set('laravel-crm.teams', true);

    $teamAUser = linkedinApiUser([
        'current_team_id' => 1,
        'team_ids' => json_encode([1]),
    ]);

    $campaignA = makeActiveCampaign(['name' => 'Team A Campaign', 'team_id' => 1]);
    $campaignB = makeActiveCampaign(['name' => 'Team B Campaign', 'team_id' => 2]);

    $recipientA = makeDueRecipient($campaignA, ['team_id' => 1, 'normalized_linkedin_slug' => 'team-a-lead']);
    $recipientB = makeDueRecipient($campaignB, ['team_id' => 2, 'normalized_linkedin_slug' => 'team-b-lead']);

    // Status only shows Team A's campaign
    $status = $this->withHeaders(linkedinApiHeaders($teamAUser))
        ->getJson('/crm/api/v2/linkedin/status')
        ->assertOk()
        ->json('data');

    expect($status['active_campaigns_count'])->toBe(1);
    expect($status['active_campaigns'][0]['id'])->toBe($campaignA->external_id);
    expect($status['due_tasks_count'])->toBe(1);

    // Claim only leases Team A's recipient
    $claimed = $this->withHeaders(linkedinApiHeaders($teamAUser))
        ->postJson('/crm/api/v2/linkedin/tasks/claim', ['worker_id' => 'ext-a'])
        ->assertOk()
        ->json('data');

    expect($claimed['recipient_external_id'])->toBe($recipientA->external_id);

    // Attempting to complete Team B's recipient returns 404
    $this->withHeaders(linkedinApiHeaders($teamAUser))
        ->postJson("/crm/api/v2/linkedin/tasks/{$recipientB->external_id}/complete", [
            'lease_token' => 'any-token',
        ])
        ->assertStatus(404);

    // Passing X-Team-ID for a non-member team returns 403
    $this->withHeaders(linkedinApiHeaders($teamAUser, teamId: 2))
        ->getJson('/crm/api/v2/linkedin/status')
        ->assertStatus(403);
});

test('CORS middleware allows chrome-extension origins safely without weakening auth', function () {
    $extensionOrigin = 'chrome-extension://abcdefghijklmnopabcdefghijklmnop';

    // Preflight OPTIONS succeeds without Bearer token and returns exact extension origin
    $preflight = $this->withHeaders([
        'Origin' => $extensionOrigin,
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Authorization, Content-Type, X-Team-ID',
    ])->options('/crm/api/v2/linkedin/tasks/claim');

    $preflight->assertStatus(204);
    expect($preflight->headers->get('Access-Control-Allow-Origin'))->toBe($extensionOrigin);
    expect($preflight->headers->get('Access-Control-Allow-Headers'))->toContain('Authorization');
    expect($preflight->headers->get('Access-Control-Allow-Credentials'))->toBeNull();

    // Unauthenticated GET with extension origin is still 401 (auth not weakened), with CORS header attached
    $unauth = $this->withHeaders(['Origin' => $extensionOrigin])
        ->getJson('/crm/api/v2/linkedin/status');
    $unauth->assertStatus(401);
    expect($unauth->headers->get('Access-Control-Allow-Origin'))->toBe($extensionOrigin);

    // Arbitrary web origin receives NO Access-Control-Allow-Origin
    $user = linkedinApiUser();
    $evil = $this->withHeaders(linkedinApiHeaders($user, extra: ['Origin' => 'https://evil.example']))
        ->getJson('/crm/api/v2/linkedin/status');
    $evil->assertOk();
    expect($evil->headers->get('Access-Control-Allow-Origin'))->toBeNull();

    // Configured allowlist blocks unlisted extension IDs
    config()->set('laravel-crm.api.allowed_extension_ids', ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz']);
    $blockedExt = $this->withHeaders(linkedinApiHeaders($user, extra: ['Origin' => $extensionOrigin]))
        ->getJson('/crm/api/v2/linkedin/status');
    $blockedExt->assertOk();
    expect($blockedExt->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

test('API v2 supports step_messages on claim, completed_step on complete, step_already_sent on fail, and outbound inbox sync', function () {
    $user = linkedinApiUser();
    $headers = linkedinApiHeaders($user);

    $campaign = makeActiveCampaign([
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'body' => 'Hi {{first_name}}!'],
            ['step' => 2, 'action' => 'dm', 'delay_hours' => 0, 'body' => 'DM 1 for {{first_name}}'],
            ['step' => 3, 'action' => 'dm', 'delay_hours' => 24, 'body' => 'DM 2 for {{first_name}}'],
            ['step' => 4, 'action' => 'dm', 'delay_hours' => 48, 'body' => 'DM 3 for {{first_name}}'],
        ],
    ]);

    $person = Person::create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    $recipient = makeDueRecipient($campaign, [
        'person_id' => $person->id,
        'normalized_linkedin_slug' => 'grace-hopper',
        'linkedin_url' => 'https://www.linkedin.com/in/grace-hopper/',
        'status' => LinkedinCampaignRecipient::STATUS_CONNECTED,
        'current_step' => 1,
        'connected_at' => now()->subHour(),
    ]);

    // 1. Claim includes step_messages and outbound_messages_count
    $claim = $this->withHeaders($headers)
        ->postJson('/crm/api/v2/linkedin/tasks/claim', ['worker_id' => 'ext-copilot'])
        ->assertOk()
        ->json('data');

    expect($claim['action'])->toBe('dm')
        ->and($claim['step'])->toBe(2)
        ->and($claim['outbound_messages_count'])->toBe(0)
        ->and($claim['step_messages'])->toHaveCount(3)
        ->and($claim['step_messages'][0]['label'])->toBe('DM 1')
        ->and($claim['step_messages'][0]['body'])->toBe('DM 1 for Grace');

    // 2. Fail with step_already_sent reconciles forward instead of failing
    $fail = $this->withHeaders($headers)
        ->postJson("/crm/api/v2/linkedin/tasks/{$recipient->external_id}/fail", [
            'lease_token' => $claim['lease_token'],
            'error_code' => 'step_already_sent',
            'error_message' => 'Intro already sent',
            'metadata' => [
                'prior_outbound_count' => 1,
                'last_sender' => 'self',
                'last_outbound_snippet' => 'DM 1 for Grace',
            ],
        ])
        ->assertOk()
        ->json('data');

    expect($fail['status'])->toBe(LinkedinCampaignRecipient::STATUS_IN_SEQUENCE)
        ->and($fail['current_step'])->toBe(2)
        ->and($fail['outbound_messages_count'])->toBe(1)
        ->and($fail['last_error_code'])->toBeNull();

    // 3. Manual outbound DM via inbox/sync advances to step 3 and deduplicates on retry
    $sync1 = $this->withHeaders($headers)
        ->postJson('/crm/api/v2/linkedin/inbox/sync', [
            'outbound' => [
                [
                    'slug' => 'grace-hopper',
                    'snippet' => 'You: DM 2 for Grace manually sent',
                    'sent_at' => now()->toIso8601String(),
                ],
            ],
        ])
        ->assertOk()
        ->json('data');

    expect($sync1)->toBe([
        'accepted' => 0,
        'replied' => 0,
        'outbound_synced' => 1,
        'updated_recipients' => 1,
    ]);
    expect($recipient->fresh()->current_step)->toBe(3);

    $sync2 = $this->withHeaders($headers)
        ->postJson('/crm/api/v2/linkedin/inbox/sync', [
            'outbound' => [
                [
                    'slug' => 'grace-hopper',
                    'snippet' => 'DM 2 for Grace manually sent',
                ],
            ],
        ])
        ->assertOk()
        ->json('data');

    expect($sync2['outbound_synced'])->toBe(0);
});
