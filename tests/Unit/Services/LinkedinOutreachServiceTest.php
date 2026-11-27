<?php

use Carbon\Carbon;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachEvent;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachMessage;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Services\LinkedinOutreachService;

afterEach(function () {
    Carbon::setTestNow();
});

test('enrollLeads resolves lead and person linkedin urls, skips non-personal urls, and deduplicates per team', function () {
    $service = new LinkedinOutreachService;

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Enrollment Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
    ]);

    $otherCampaignSameTeam = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Existing Active Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
    ]);

    $otherTeamCampaign = LinkedinCampaign::create([
        'team_id' => 2,
        'name' => 'Other Team Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
    ]);

    // Pre-existing active recipient on same team
    LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $otherCampaignSameTeam->id,
        'linkedin_url' => 'https://www.linkedin.com/in/already-enrolled/',
        'status' => LinkedinCampaignRecipient::STATUS_CONNECT_SENT,
    ]);

    // Pre-existing recipient on DIFFERENT team (should NOT block team 1)
    LinkedinCampaignRecipient::create([
        'team_id' => 2,
        'linkedin_campaign_id' => $otherTeamCampaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/other-team-prospect/',
        'status' => LinkedinCampaignRecipient::STATUS_COMPLETED,
    ]);

    $leadDirect = Lead::create([
        'team_id' => 1,
        'title' => 'Direct URL Lead',
        'linkedin' => 'https://www.linkedin.com/in/Other-Team-Prospect/?utm_source=test',
    ]);

    $personWithUrl = Person::create([
        'team_id' => 1,
        'first_name' => 'Sarah',
        'last_name' => 'Connor',
        'linkedin' => 'linkedin.com/in/sarah-connor-99/',
    ]);
    $leadViaPerson = Lead::create([
        'team_id' => 1,
        'title' => 'Fallback Person URL Lead',
        'person_id' => $personWithUrl->id,
        'linkedin' => null,
    ]);

    $leadDuplicateExisting = Lead::create([
        'team_id' => 1,
        'title' => 'Duplicate Existing Lead',
        'linkedin' => 'https://za.linkedin.com/in/already-enrolled',
    ]);

    $leadDuplicateInBatch = Lead::create([
        'team_id' => 1,
        'title' => 'Duplicate Batch Lead',
        'linkedin' => 'https://www.linkedin.com/in/sarah-connor-99?trk=abc',
    ]);

    $leadCompanyPage = Lead::create([
        'team_id' => 1,
        'title' => 'Company Page Only',
        'linkedin' => 'https://www.linkedin.com/company/acme-corp/',
    ]);

    $leadNoUrl = Lead::create([
        'team_id' => 1,
        'title' => 'No URL Lead',
        'linkedin' => null,
    ]);

    $summary = $service->enrollLeads($campaign, collect([
        $leadDirect,
        $leadViaPerson,
        $leadDuplicateExisting,
        $leadDuplicateInBatch,
        $leadCompanyPage,
        $leadNoUrl,
    ]));

    expect($summary['enrolled'])->toBe(2)
        ->and($summary['skipped_no_url'])->toBe(2)
        ->and($summary['skipped_duplicate'])->toBe(2)
        ->and($campaign->fresh()->total_recipients)->toBe(2)
        ->and(collect($summary['recipients'])->pluck('normalized_linkedin_slug')->all())
        ->toEqualCanonicalizing(['other-team-prospect', 'sarah-connor-99']);
});

test('claimNextTask reclaims expired leases, claims atomically, renders payload, and completeTask logs CRM activity', function () {
    $now = Carbon::parse('2026-11-25 10:00:00', 'Africa/Johannesburg')->utc();
    Carbon::setTestNow($now);

    $service = new LinkedinOutreachService;

    $connectTemplate = LinkedinTemplate::create([
        'team_id' => 1,
        'name' => 'Connect Note',
        'type' => LinkedinTemplate::TYPE_CONNECT_NOTE,
        'body' => 'Hi {{first_name}}, saw you lead {{company|your team}}.',
    ]);

    $dmTemplate = LinkedinTemplate::create([
        'team_id' => 1,
        'name' => 'DM 1',
        'type' => LinkedinTemplate::TYPE_DM,
        'body' => 'Thanks for connecting {{first_name}}!',
    ]);

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Founders Q4',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'min_delay_seconds' => 180,
        'max_delay_seconds' => 240,
        'timezone' => 'Africa/Johannesburg',
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'template_id' => $connectTemplate->id, 'delay_hours' => 0],
            ['step' => 2, 'action' => 'dm', 'template_id' => $dmTemplate->id, 'delay_hours' => 24],
        ],
    ]);

    $org = Organization::create(['team_id' => 1, 'name' => 'Hyperion Labs']);
    $person = Person::create([
        'team_id' => 1,
        'first_name' => 'Elena',
        'last_name' => 'Vance',
        'organization_id' => $org->id,
    ]);
    $lead = Lead::create([
        'team_id' => 1,
        'title' => 'Hyperion Labs - CTO',
        'person_id' => $person->id,
        'organization_id' => $org->id,
    ]);

    // Expired lease should be reclaimed and then claimed by worker-2
    $recipient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $lead->id,
        'person_id' => $person->id,
        'linkedin_url' => 'https://www.linkedin.com/in/elena-vance',
        'status' => LinkedinCampaignRecipient::STATUS_LEASED,
        'lease_token' => 'stale-token',
        'leased_at' => $now->copy()->subMinutes(10),
        'lease_expires_at' => $now->copy()->subMinutes(2),
        'worker_id' => 'dead-worker',
    ]);

    $task = $service->claimNextTask(1, 'worker-2', 300);

    expect($task)->not->toBeNull()
        ->and($task['recipient_external_id'])->toBe($recipient->external_id)
        ->and($task['lease_token'])->not->toBe('stale-token')
        ->and($task['action'])->toBe('connect')
        ->and($task['step'])->toBe(1)
        ->and($task['linkedin_url'])->toBe('https://www.linkedin.com/in/elena-vance')
        ->and($task['message_body'])->toBe('Hi Elena, saw you lead Hyperion Labs.')
        ->and($task['person_name'])->toBe('Elena Vance')
        ->and($task['company_name'])->toBe('Hyperion Labs');

    $recipient->refresh();
    expect($recipient->status)->toBe(LinkedinCampaignRecipient::STATUS_LEASED)
        ->and($recipient->worker_id)->toBe('worker-2')
        ->and($campaign->fresh()->last_worker_heartbeat_at)->not->toBeNull();

    // Second worker cannot claim the already-leased recipient
    expect($service->claimNextTask(1, 'worker-3'))->toBeNull();

    // Wrong lease token is rejected
    expect(fn () => $service->completeTask($recipient, 'wrong-token', []))
        ->toThrow(RuntimeException::class);

    // Completing step 1 (connect) transitions to connect_sent and logs CRM Note + Activity
    $service->completeTask($recipient, $task['lease_token'], [
        'message_body' => $task['message_body'],
    ]);

    $recipient->refresh();
    expect($recipient->status)->toBe(LinkedinCampaignRecipient::STATUS_CONNECT_SENT)
        ->and($recipient->current_step)->toBe(1)
        ->and($recipient->connect_sent_at)->not->toBeNull()
        ->and($recipient->lease_token)->toBeNull()
        ->and($campaign->fresh()->connects_sent_count)->toBe(1)
        ->and(LinkedinOutreachEvent::where('action', LinkedinOutreachEvent::ACTION_CONNECT_SENT)->count())->toBe(1)
        ->and(Note::where('noteable_id', $lead->id)->count())->toBe(1)
        ->and(Activity::where('timelineable_id', $lead->id)->count())->toBe(1);
});

test('failTask handles already_connected fast-forward, pending_invite, skip codes, and transient retries', function () {
    $now = Carbon::parse('2026-11-25 10:00:00', 'Africa/Johannesburg')->utc();
    Carbon::setTestNow($now);

    $service = new LinkedinOutreachService;

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Error Handling Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'min_delay_seconds' => 0,
        'timezone' => 'Africa/Johannesburg',
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'body' => 'Connect'],
            ['step' => 2, 'action' => 'dm', 'body' => 'Hello DM', 'delay_hours' => 0],
        ],
    ]);

    // 1. already_connected fast-forwards directly to first DM step
    $r1 = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/already-conn',
        'status' => LinkedinCampaignRecipient::STATUS_LEASED,
        'lease_token' => 'tok-1',
        'lease_expires_at' => $now->copy()->addMinutes(5),
    ]);

    $service->failTask($r1, 'tok-1', 'already_connected');
    $r1->refresh();

    expect($r1->status)->toBe(LinkedinCampaignRecipient::STATUS_CONNECTED)
        ->and($r1->connected_at)->not->toBeNull()
        ->and($r1->current_step)->toBe(1)
        ->and($r1->next_action_at)->not->toBeNull();

    // Next claim should immediately yield step 2 (dm)
    $dmTask = $service->claimNextTask(1, 'worker-1');
    expect($dmTask)->not->toBeNull()
        ->and($dmTask['action'])->toBe('dm')
        ->and($dmTask['step'])->toBe(2);

    // Completing the final DM step marks recipient completed
    $service->completeTask($r1->fresh(), $dmTask['lease_token'], ['message_body' => 'Hello DM']);
    expect($r1->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_COMPLETED)
        ->and($r1->fresh()->completed_at)->not->toBeNull();

    // 2. pending_invite marks connect_sent
    $r2 = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/pending-inv',
        'status' => LinkedinCampaignRecipient::STATUS_LEASED,
        'lease_token' => 'tok-2',
        'lease_expires_at' => $now->copy()->addMinutes(5),
    ]);

    $service->failTask($r2, 'tok-2', 'pending_invite');
    expect($r2->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_CONNECT_SENT);

    // 3. email_required / profile_unavailable / cannot_message mark skipped
    foreach (['email_required', 'profile_unavailable', 'cannot_message'] as $idx => $code) {
        $rSkip = LinkedinCampaignRecipient::create([
            'team_id' => 1,
            'linkedin_campaign_id' => $campaign->id,
            'linkedin_url' => 'https://www.linkedin.com/in/skip-'.$idx,
            'status' => LinkedinCampaignRecipient::STATUS_LEASED,
            'lease_token' => 'tok-skip-'.$idx,
            'lease_expires_at' => $now->copy()->addMinutes(5),
        ]);

        $service->failTask($rSkip, 'tok-skip-'.$idx, $code, 'Reason '.$code);
        expect($rSkip->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_SKIPPED)
            ->and($rSkip->fresh()->last_error_code)->toBe($code);
    }

    // 4. transient_dom_error retries twice with backoff, then marks failed on 3rd attempt
    $rTransient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/transient-user',
        'status' => LinkedinCampaignRecipient::STATUS_LEASED,
        'lease_token' => 'tok-t1',
        'lease_expires_at' => $now->copy()->addMinutes(5),
    ]);

    $service->failTask($rTransient, 'tok-t1', 'transient_dom_error', 'Button missing');
    $rTransient->refresh();
    expect($rTransient->status)->toBe(LinkedinCampaignRecipient::STATUS_QUEUED)
        ->and($rTransient->attempt_count)->toBe(1)
        ->and($rTransient->next_action_at->isAfter($now))->toBeTrue();

    $rTransient->update(['status' => LinkedinCampaignRecipient::STATUS_LEASED, 'lease_token' => 'tok-t2']);
    $service->failTask($rTransient, 'tok-t2', 'transient_dom_error', 'Button missing again');
    $rTransient->refresh();
    expect($rTransient->status)->toBe(LinkedinCampaignRecipient::STATUS_QUEUED)
        ->and($rTransient->attempt_count)->toBe(2);

    $rTransient->update(['status' => LinkedinCampaignRecipient::STATUS_LEASED, 'lease_token' => 'tok-t3']);
    $service->failTask($rTransient, 'tok-t3', 'transient_dom_error', 'Third failure');
    $rTransient->refresh();
    expect($rTransient->status)->toBe(LinkedinCampaignRecipient::STATUS_FAILED)
        ->and($rTransient->attempt_count)->toBe(3)
        ->and($campaign->fresh()->failed_count)->toBe(1);
});

test('failTask trips circuit breaker across all active campaigns for the team on rate_limited or captcha_detected', function (string $errorCode) {
    $now = Carbon::parse('2026-11-25 10:00:00', 'Africa/Johannesburg')->utc();
    Carbon::setTestNow($now);

    $service = new LinkedinOutreachService;

    $campaign1 = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Team 1 Campaign A',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
    ]);

    $campaign2 = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Team 1 Campaign B',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
    ]);

    $otherTeamCampaign = LinkedinCampaign::create([
        'team_id' => 2,
        'name' => 'Team 2 Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
    ]);

    $recipient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign1->id,
        'linkedin_url' => 'https://www.linkedin.com/in/breaker-trigger',
        'status' => LinkedinCampaignRecipient::STATUS_LEASED,
        'lease_token' => 'tok-cb',
        'lease_expires_at' => $now->copy()->addMinutes(5),
    ]);

    $service->failTask($recipient, 'tok-cb', $errorCode, 'LinkedIn challenge triggered');

    expect($recipient->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_QUEUED)
        ->and($recipient->fresh()->lease_token)->toBeNull()
        ->and($campaign1->fresh()->status)->toBe(LinkedinCampaign::STATUS_CIRCUIT_BROKEN)
        ->and($campaign1->fresh()->circuit_broken_reason)->toBe($errorCode)
        ->and($campaign1->fresh()->circuit_broken_at)->not->toBeNull()
        ->and($campaign2->fresh()->status)->toBe(LinkedinCampaign::STATUS_CIRCUIT_BROKEN)
        ->and($campaign2->fresh()->circuit_broken_reason)->toBe($errorCode)
        ->and($otherTeamCampaign->fresh()->status)->toBe(LinkedinCampaign::STATUS_ACTIVE)
        ->and(LinkedinOutreachEvent::where('action', LinkedinOutreachEvent::ACTION_CIRCUIT_BREAKER_TRIPPED)->count())->toBe(2);
})->with([
    'rate_limited',
    'captcha_detected',
    'auth_expired',
]);

test('syncInboxEvents promotes accepted connects and halts sequence while marking lead engaged on reply', function () {
    $now = Carbon::parse('2026-11-25 10:00:00', 'Africa/Johannesburg')->utc();
    Carbon::setTestNow($now);

    $service = new LinkedinOutreachService;

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Inbox Sync Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'min_delay_seconds' => 60,
        'max_delay_seconds' => 120,
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'body' => 'Connect'],
            ['step' => 2, 'action' => 'dm', 'body' => 'Follow up DM', 'delay_hours' => 12],
        ],
    ]);

    $leadAccepted = Lead::create([
        'team_id' => 1,
        'title' => 'Accepted Prospect',
        'relay_status' => 'active',
    ]);

    $leadReplied = Lead::create([
        'team_id' => 1,
        'title' => 'Replied Prospect',
        'relay_status' => 'active',
    ]);

    $acceptedRecipient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $leadAccepted->id,
        'linkedin_url' => 'https://www.linkedin.com/in/accepted-user/',
        'status' => LinkedinCampaignRecipient::STATUS_CONNECT_SENT,
        'current_step' => 1,
        'connect_sent_at' => $now->copy()->subDay(),
    ]);

    $repliedRecipient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $leadReplied->id,
        'linkedin_url' => 'https://www.linkedin.com/in/replied-user/',
        'status' => LinkedinCampaignRecipient::STATUS_IN_SEQUENCE,
        'current_step' => 2,
        'next_action_at' => $now->copy()->addHours(24),
    ]);

    $result = $service->syncInboxEvents(
        1,
        ['accepted-user'],
        [
            [
                'slug' => 'replied-user',
                'snippet' => 'Yes, happy to jump on a quick call Thursday!',
                'replied_at' => $now->toIso8601String(),
            ],
        ]
    );

    expect($result)->toBe(['accepted' => 1, 'replied' => 1, 'outbound_synced' => 0]);

    $acceptedRecipient->refresh();
    expect($acceptedRecipient->status)->toBe(LinkedinCampaignRecipient::STATUS_CONNECTED)
        ->and($acceptedRecipient->connected_at)->not->toBeNull()
        ->and($acceptedRecipient->next_action_at)->not->toBeNull()
        ->and($acceptedRecipient->next_action_at->greaterThanOrEqualTo($now->copy()->addHours(12)->addSeconds(60)))->toBeTrue();

    $repliedRecipient->refresh();
    expect($repliedRecipient->status)->toBe(LinkedinCampaignRecipient::STATUS_REPLIED)
        ->and($repliedRecipient->replied_at)->not->toBeNull()
        ->and($repliedRecipient->next_action_at)->toBeNull()
        ->and($leadReplied->fresh()->relay_status)->toBe('engaged')
        ->and($campaign->fresh()->accepted_count)->toBe(1)
        ->and($campaign->fresh()->replied_count)->toBe(1);

    $replyNote = Note::where('noteable_id', $leadReplied->id)->first();
    expect($replyNote)->not->toBeNull()
        ->and($replyNote->content)->toContain('Yes, happy to jump on a quick call Thursday!')
        ->and(Activity::where('timelineable_id', $leadReplied->id)->where('recordable_id', $replyNote->id)->count())->toBe(1);
});

test('syncInboxEvents handles manual outbound DMs idempotently and advances stage and next_action_at', function () {
    $now = Carbon::parse('2026-11-27 12:00:00', 'UTC');
    Carbon::setTestNow($now);

    $service = new LinkedinOutreachService;

    $pipeline = Pipeline::create([
        'name' => 'Lead Funnel',
        'model' => Lead::class,
        'default' => true,
        'order' => 1,
    ]);
    $stageCold = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Cold Prospect', 'order' => 1]);
    $stageConnectSent = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Connection Request Sent', 'order' => 2]);
    $stageConnectedDm = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Connected / DM Sent', 'order' => 3]);

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Manual Sync Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'min_delay_seconds' => 0,
        'max_delay_seconds' => 0,
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'body' => 'Connect'],
            ['step' => 2, 'action' => 'dm', 'body' => 'DM 1', 'delay_hours' => 0],
            ['step' => 3, 'action' => 'dm', 'body' => 'DM 2', 'delay_hours' => 48],
        ],
    ]);

    $lead = Lead::create([
        'team_id' => 1,
        'title' => 'John Smith Lead',
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stageConnectSent->id,
        'pipeline_stage_order' => 2,
    ]);

    $recipient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $lead->id,
        'linkedin_url' => 'https://www.linkedin.com/in/john-smith/',
        'status' => LinkedinCampaignRecipient::STATUS_CONNECT_SENT,
        'current_step' => 1,
        'connect_sent_at' => $now->copy()->subDay(),
    ]);

    $sentAt = $now->copy()->subMinutes(15);

    // First sync with "You:" prefix records outbound message, advances step to 2, schedules step 3 (+48h), advances stage
    $first = $service->syncInboxEvents(1, [], [], [
        [
            'slug' => 'john-smith',
            'snippet' => 'You: Hey John, thanks for connecting! Check https://dubstrata.com/research',
            'sent_at' => $sentAt->toIso8601String(),
        ],
    ]);

    expect($first)->toBe(['accepted' => 0, 'replied' => 0, 'outbound_synced' => 1]);

    $recipient->refresh();
    expect($recipient->status)->toBe(LinkedinCampaignRecipient::STATUS_IN_SEQUENCE)
        ->and($recipient->current_step)->toBe(2)
        ->and($recipient->connected_at)->not->toBeNull()
        ->and($recipient->last_dm_sent_at->equalTo($sentAt))->toBeTrue()
        ->and($recipient->next_action_at->equalTo($sentAt->copy()->addHours(48)))->toBeTrue()
        ->and($lead->fresh()->pipeline_stage_id)->toBe($stageConnectedDm->id)
        ->and(LinkedinOutreachMessage::where('linkedin_campaign_recipient_id', $recipient->id)->count())->toBe(1);

    // Second sync with same text (without "You:" and different URL/punctuation) is an idempotent no-op
    $second = $service->syncInboxEvents(1, [], [], [
        [
            'slug' => 'john-smith',
            'snippet' => 'hey john thanks for connecting check https://other.example/url',
            'sent_at' => $now->toIso8601String(),
        ],
    ]);

    expect($second)->toBe(['accepted' => 0, 'replied' => 0, 'outbound_synced' => 0])
        ->and($recipient->fresh()->current_step)->toBe(2)
        ->and(LinkedinOutreachMessage::where('linkedin_campaign_recipient_id', $recipient->id)->count())->toBe(1);
});

test('completeTask supports completed_step and prior_outbound_count jumps and logs idempotent message', function () {
    $now = Carbon::parse('2026-11-27 10:00:00', 'UTC');
    Carbon::setTestNow($now);

    $service = new LinkedinOutreachService;

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Co-Pilot Jump Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'min_delay_seconds' => 0,
        'max_delay_seconds' => 0,
        'active_days' => [1, 2, 3, 4, 5, 6, 7],
        'active_start_hour' => 0,
        'active_end_hour' => 24,
        'timezone' => 'UTC',
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'body' => 'Connect'],
            ['step' => 2, 'action' => 'dm', 'body' => 'DM 1', 'delay_hours' => 0],
            ['step' => 3, 'action' => 'dm', 'body' => 'DM 2', 'delay_hours' => 24],
            ['step' => 4, 'action' => 'dm', 'body' => 'DM 3', 'delay_hours' => 72],
        ],
    ]);

    $recipient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/jump-prospect/',
        'status' => LinkedinCampaignRecipient::STATUS_CONNECTED,
        'current_step' => 1,
        'connected_at' => $now->copy()->subHour(),
        'next_action_at' => $now->copy()->subMinute(),
    ]);

    $task = $service->claimNextTask(1, 'copilot-worker');
    expect($task['step'])->toBe(2)
        ->and($task['step_messages'])->toHaveCount(3);

    // Co-Pilot user selects DM 2 (step 3) with 1 prior outbound message
    $service->completeTask($recipient->fresh(), $task['lease_token'], [
        'sent_text' => 'Hey, floating this back up...',
        'completed_step' => 3,
        'prior_outbound_count' => 1,
    ]);

    $recipient->refresh();
    expect($recipient->status)->toBe(LinkedinCampaignRecipient::STATUS_IN_SEQUENCE)
        ->and($recipient->current_step)->toBe(3)
        ->and($recipient->next_action_at->equalTo($now->copy()->addHours(72)))->toBeTrue()
        ->and(LinkedinOutreachMessage::where('linkedin_campaign_recipient_id', $recipient->id)->count())->toBe(1);

    // Later inbox sync reporting the exact same worker-sent DM is ignored (no double-advance)
    $sync = $service->syncInboxEvents(1, [], [], [
        ['slug' => 'jump-prospect', 'snippet' => 'You: Hey, floating this back up...'],
    ]);
    expect($sync['outbound_synced'])->toBe(0)
        ->and($recipient->fresh()->current_step)->toBe(3);
});

test('failTask reconciles step_already_sent without failing or burning DM quota', function () {
    $now = Carbon::parse('2026-11-27 10:00:00', 'UTC');
    Carbon::setTestNow($now);

    $service = new LinkedinOutreachService;

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Reconcile Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'min_delay_seconds' => 0,
        'max_delay_seconds' => 0,
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'body' => 'Connect'],
            ['step' => 2, 'action' => 'dm', 'body' => 'DM 1', 'delay_hours' => 0],
            ['step' => 3, 'action' => 'dm', 'body' => 'DM 2', 'delay_hours' => 24],
        ],
    ]);

    $recipient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/reconcile-user/',
        'status' => LinkedinCampaignRecipient::STATUS_LEASED,
        'current_step' => 1,
        'lease_token' => 'tok-reconcile',
        'lease_expires_at' => $now->copy()->addMinutes(5),
    ]);

    $service->failTask(
        $recipient,
        'tok-reconcile',
        'step_already_sent',
        'Skipped DM: prior intro already sent.',
        [
            'prior_outbound_count' => 1,
            'last_sender' => 'self',
            'last_outbound_snippet' => 'Hey John, thanks for connecting!',
        ]
    );

    $recipient->refresh();
    expect($recipient->status)->toBe(LinkedinCampaignRecipient::STATUS_IN_SEQUENCE)
        ->and($recipient->current_step)->toBe(2)
        ->and($recipient->last_error_code)->toBeNull()
        ->and($recipient->next_action_at->equalTo($now->copy()->addHours(24)))->toBeTrue()
        ->and($campaign->fresh()->failed_count)->toBe(0)
        ->and(LinkedinOutreachEvent::where('action', LinkedinOutreachEvent::ACTION_DM_SENT)->count())->toBe(0)
        ->and(LinkedinOutreachEvent::where('action', LinkedinOutreachEvent::ACTION_STEP_RECONCILED)->count())->toBe(1)
        ->and(LinkedinOutreachMessage::where('linkedin_campaign_recipient_id', $recipient->id)->count())->toBe(1);
});

test('claimNextTask prioritizes DMs over connects, skips stop-outreach stages, and enforces independent quotas', function () {
    $now = Carbon::parse('2026-11-27 10:00:00', 'UTC');
    Carbon::setTestNow($now);

    $service = new LinkedinOutreachService;

    $pipeline = Pipeline::create([
        'name' => 'Lead Funnel',
        'model' => Lead::class,
        'default' => true,
        'order' => 1,
    ]);
    $stageCold = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Cold Prospect', 'order' => 1]);
    $stageQualified = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Engaged / Qualified', 'order' => 4]);

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Priority Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'daily_connect_limit' => 1,
        'daily_dm_limit' => 10,
        'min_delay_seconds' => 0,
        'max_delay_seconds' => 0,
        'active_days' => [1, 2, 3, 4, 5, 6, 7],
        'active_start_hour' => 0,
        'active_end_hour' => 24,
        'timezone' => 'UTC',
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'body' => 'Hi {{first_name}}'],
            ['step' => 2, 'action' => 'dm', 'body' => 'DM 1 {{first_name}}', 'delay_hours' => 0],
        ],
    ]);

    // Exhaust daily connect quota (1/1 used today)
    LinkedinOutreachEvent::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'action' => LinkedinOutreachEvent::ACTION_CONNECT_SENT,
        'step' => 1,
        'occurred_at' => $now->copy()->subMinutes(10),
    ]);

    // Stop-outreach lead (Qualified) should never be claimed even though connected & due
    $qualifiedLead = Lead::create([
        'team_id' => 1,
        'title' => 'Qualified Lead',
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stageQualified->id,
        'pipeline_stage_order' => 4,
    ]);
    LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $qualifiedLead->id,
        'linkedin_url' => 'https://www.linkedin.com/in/qualified-lead/',
        'status' => LinkedinCampaignRecipient::STATUS_CONNECTED,
        'current_step' => 1,
        'next_action_at' => $now->copy()->subHours(2),
    ]);

    // Cold connect recipient (blocked by exhausted connect quota)
    $coldLead = Lead::create([
        'team_id' => 1,
        'title' => 'Cold Lead',
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stageCold->id,
        'pipeline_stage_order' => 1,
    ]);
    LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $coldLead->id,
        'linkedin_url' => 'https://www.linkedin.com/in/cold-lead/',
        'status' => LinkedinCampaignRecipient::STATUS_QUEUED,
        'current_step' => 0,
        'next_action_at' => $now->copy()->subHours(3),
    ]);

    // Connected DM recipient (should be claimed despite exhausted connect quota!)
    $dmRecipient = LinkedinCampaignRecipient::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'linkedin_url' => 'https://www.linkedin.com/in/ready-dm/',
        'status' => LinkedinCampaignRecipient::STATUS_CONNECTED,
        'current_step' => 1,
        'next_action_at' => $now->copy()->subMinute(),
    ]);

    $claimed = $service->claimNextTask(1, 'worker-priority');
    expect($claimed)->not->toBeNull()
        ->and($claimed['recipient_external_id'])->toBe($dmRecipient->external_id)
        ->and($claimed['action'])->toBe('dm')
        ->and($claimed['step'])->toBe(2)
        ->and($claimed['outbound_messages_count'])->toBe(0);

    // Next claim should return null (qualified lead skipped, cold connect blocked by connect quota)
    expect($service->claimNextTask(1, 'worker-priority'))->toBeNull();
});
