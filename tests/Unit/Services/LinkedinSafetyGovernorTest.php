<?php

use Carbon\Carbon;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachEvent;
use VentureDrake\LaravelCrm\Services\LinkedinSafetyGovernor;

afterEach(function () {
    Carbon::setTestNow();
});

test('safety governor blocks non-active campaigns', function (string $status) {
    $governor = new LinkedinSafetyGovernor;
    $now = Carbon::parse('2026-11-25 10:00:00', 'Africa/Johannesburg'); // Wednesday 10:00

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Status Check Campaign',
        'status' => $status,
        'timezone' => 'Africa/Johannesburg',
    ]);

    $result = $governor->evaluate($campaign, 'connect', $now);

    expect($result['allowed'])->toBeFalse()
        ->and($result['reason'])->toBe('campaign_'.$status)
        ->and($result['next_eligible_at'])->toBeNull();
})->with([
    LinkedinCampaign::STATUS_DRAFT,
    LinkedinCampaign::STATUS_PAUSED,
    LinkedinCampaign::STATUS_CIRCUIT_BROKEN,
    LinkedinCampaign::STATUS_COMPLETED,
]);

test('safety governor allows active campaign within working hours and reports remaining quota', function () {
    $governor = new LinkedinSafetyGovernor;
    $now = Carbon::parse('2026-11-25 10:00:00', 'Africa/Johannesburg'); // Wednesday 10:00

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Active Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'daily_connect_limit' => 10,
        'weekly_connect_limit' => 50,
        'daily_dm_limit' => 20,
        'active_days' => [1, 2, 3, 4, 5],
        'active_start_hour' => 8,
        'active_end_hour' => 18,
        'timezone' => 'Africa/Johannesburg',
    ]);

    $result = $governor->evaluate($campaign, 'connect', $now);

    expect($result['allowed'])->toBeTrue()
        ->and($result['reason'])->toBeNull()
        ->and($result['remaining_today'])->toBe(['connect' => 10, 'dm' => 20])
        ->and($result['next_eligible_at'])->toBeNull();
});

test('safety governor blocks outside working hours and on inactive days with next_eligible_at', function () {
    $governor = new LinkedinSafetyGovernor;

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Business Hours Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'active_days' => [1, 2, 3, 4, 5],
        'active_start_hour' => 8,
        'active_end_hour' => 18,
        'timezone' => 'Africa/Johannesburg',
    ]);

    // Wednesday 07:30 local -> next eligible is Wednesday 08:00 local
    $earlyMorning = Carbon::parse('2026-11-25 07:30:00', 'Africa/Johannesburg');
    $earlyResult = $governor->evaluate($campaign, 'connect', $earlyMorning);

    expect($earlyResult['allowed'])->toBeFalse()
        ->and($earlyResult['reason'])->toBe('outside_working_hours')
        ->and($earlyResult['next_eligible_at'])->toBeInstanceOf(Carbon::class)
        ->and($earlyResult['next_eligible_at']->copy()->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i:s'))
        ->toBe('2026-11-25 08:00:00');

    // Friday 18:00 local (end hour is exclusive) -> next eligible is Monday 08:00 local
    $fridayEvening = Carbon::parse('2026-11-27 18:00:00', 'Africa/Johannesburg');
    $weekendResult = $governor->evaluate($campaign, 'dm', $fridayEvening);

    expect($weekendResult['allowed'])->toBeFalse()
        ->and($weekendResult['reason'])->toBe('outside_working_hours')
        ->and($weekendResult['next_eligible_at']->copy()->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i:s'))
        ->toBe('2026-11-30 08:00:00');
});

test('safety governor enforces team-wide daily connect cap across multiple campaigns', function () {
    $governor = new LinkedinSafetyGovernor;
    $now = Carbon::parse('2026-11-25 11:00:00', 'Africa/Johannesburg');

    $campaignA = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Campaign A',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'daily_connect_limit' => 2,
        'weekly_connect_limit' => 50,
        'min_delay_seconds' => 0,
        'timezone' => 'Africa/Johannesburg',
    ]);

    $campaignB = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Campaign B',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'daily_connect_limit' => 2,
        'weekly_connect_limit' => 50,
        'min_delay_seconds' => 0,
        'timezone' => 'Africa/Johannesburg',
    ]);

    $otherTeamCampaign = LinkedinCampaign::create([
        'team_id' => 2,
        'name' => 'Other Team Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'daily_connect_limit' => 2,
        'weekly_connect_limit' => 50,
        'min_delay_seconds' => 0,
        'timezone' => 'Africa/Johannesburg',
    ]);

    // 1 connect on campaign A, 1 connect on campaign B => team 1 has hit cap of 2
    LinkedinOutreachEvent::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaignA->id,
        'action' => LinkedinOutreachEvent::ACTION_CONNECT_SENT,
        'occurred_at' => $now->copy()->subHours(2)->utc(),
    ]);

    LinkedinOutreachEvent::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaignB->id,
        'action' => LinkedinOutreachEvent::ACTION_CONNECT_SENT,
        'occurred_at' => $now->copy()->subHour()->utc(),
    ]);

    $teamOneResult = $governor->evaluate($campaignA, 'connect', $now);
    $teamTwoResult = $governor->evaluate($otherTeamCampaign, 'connect', $now);

    expect($teamOneResult['allowed'])->toBeFalse()
        ->and($teamOneResult['reason'])->toBe('daily_connect_limit_reached')
        ->and($teamOneResult['remaining_today']['connect'])->toBe(0)
        ->and($teamOneResult['next_eligible_at']->copy()->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i:s'))
        ->toBe('2026-11-26 08:00:00')
        ->and($teamTwoResult['allowed'])->toBeTrue()
        ->and($teamTwoResult['remaining_today']['connect'])->toBe(2);
});

test('safety governor enforces team-wide weekly connect cap and daily dm cap', function () {
    $governor = new LinkedinSafetyGovernor;
    $now = Carbon::parse('2026-11-25 11:00:00', 'Africa/Johannesburg');

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Weekly Cap Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'daily_connect_limit' => 10,
        'weekly_connect_limit' => 3,
        'daily_dm_limit' => 1,
        'min_delay_seconds' => 0,
        'timezone' => 'Africa/Johannesburg',
    ]);

    // 3 connects over the past 5 days (none today) -> weekly cap reached
    foreach ([5, 3, 1] as $daysAgo) {
        LinkedinOutreachEvent::create([
            'team_id' => 1,
            'linkedin_campaign_id' => $campaign->id,
            'action' => LinkedinOutreachEvent::ACTION_CONNECT_SENT,
            'occurred_at' => $now->copy()->subDays($daysAgo)->utc(),
        ]);
    }

    $weeklyResult = $governor->evaluate($campaign, 'connect', $now);

    expect($weeklyResult['allowed'])->toBeFalse()
        ->and($weeklyResult['reason'])->toBe('weekly_connect_limit_reached')
        ->and($weeklyResult['remaining_today']['connect'])->toBe(0)
        ->and($weeklyResult['next_eligible_at'])->toBeInstanceOf(Carbon::class);

    // DM is still allowed until daily DM cap is hit
    expect($governor->evaluate($campaign, 'dm', $now)['allowed'])->toBeTrue();

    LinkedinOutreachEvent::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'action' => LinkedinOutreachEvent::ACTION_DM_SENT,
        'occurred_at' => $now->copy()->subMinutes(30)->utc(),
    ]);

    $dmResult = $governor->evaluate($campaign, 'dm', $now);

    expect($dmResult['allowed'])->toBeFalse()
        ->and($dmResult['reason'])->toBe('daily_dm_limit_reached')
        ->and($dmResult['remaining_today']['dm'])->toBe(0);
});

test('safety governor enforces min_delay_seconds cooldown since last outbound event', function () {
    $governor = new LinkedinSafetyGovernor;
    $now = Carbon::parse('2026-11-25 11:00:00', 'Africa/Johannesburg');

    $campaign = LinkedinCampaign::create([
        'team_id' => 1,
        'name' => 'Cooldown Campaign',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'min_delay_seconds' => 180,
        'timezone' => 'Africa/Johannesburg',
    ]);

    // Inbound reply 10 seconds ago does NOT trigger send cooldown
    LinkedinOutreachEvent::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'action' => LinkedinOutreachEvent::ACTION_REPLY_RECEIVED,
        'occurred_at' => $now->copy()->subSeconds(10)->utc(),
    ]);

    expect($governor->evaluate($campaign, 'dm', $now)['allowed'])->toBeTrue();

    // Outbound DM 60 seconds ago triggers 180s cooldown -> 120s remaining
    LinkedinOutreachEvent::create([
        'team_id' => 1,
        'linkedin_campaign_id' => $campaign->id,
        'action' => LinkedinOutreachEvent::ACTION_DM_SENT,
        'occurred_at' => $now->copy()->subSeconds(60)->utc(),
    ]);

    $cooldownResult = $governor->evaluate($campaign, 'connect', $now);

    expect($cooldownResult['allowed'])->toBeFalse()
        ->and($cooldownResult['reason'])->toBe('cooldown_active')
        ->and($cooldownResult['next_eligible_at']->equalTo($now->copy()->addSeconds(120)->utc()))->toBeTrue();

    // After 181 seconds, cooldown has elapsed
    $afterCooldown = $governor->evaluate($campaign, 'connect', $now->copy()->addSeconds(121));
    expect($afterCooldown['allowed'])->toBeTrue();
});
