<?php

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use VentureDrake\LaravelCrm\Http\Controllers\AgentTelegramWebhookController;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\Agent\CrmHeartbeatService;
use VentureDrake\LaravelCrm\Services\Agent\CrmToolsService;
use VentureDrake\LaravelCrm\Services\Agent\GeminiAgentService;
use VentureDrake\LaravelCrm\Services\Agent\TelegramBotService;

beforeEach(function () {
    Cache::forget(CrmToolsService::NOTIFICATION_STATE_KEY);
    config()->set('laravel-crm.agent.gemini_api_key', 'test-gemini-key');
    config()->set('laravel-crm.agent.telegram_bot_token', 'test-telegram-token');
    config()->set('laravel-crm.agent.telegram_admin_chat_id', '123456789');
});

it('normalizes proactivity modes and persists state across CrmToolsService', function () {
    $tools = app(CrmToolsService::class);

    expect($tools->normalizeProactivityMode('most proactive'))->toBe('high')
        ->and($tools->normalizeProactivityMode('smart_delta'))->toBe('balanced')
        ->and($tools->normalizeProactivityMode('daily digest'))->toBe('low')
        ->and($tools->normalizeProactivityMode('least proactive'))->toBe('silent');

    $updated = $tools->updateNotificationSettings([
        'mode' => 'daily digest',
        'snooze_hours' => 4,
    ]);

    expect($updated['current_mode'])->toBe('low')
        ->and($updated['cooldown_minutes'])->toBe(1440)
        ->and($updated['is_snoozed'])->toBeTrue();

    $resumed = $tools->updateNotificationSettings([
        'mode' => 'high',
        'clear_snooze' => true,
    ]);

    expect($resumed['current_mode'])->toBe('high')
        ->and($resumed['cooldown_minutes'])->toBe(60)
        ->and($resumed['is_snoozed'])->toBeFalse();
});

it('suppresses autonomous heartbeat notifications in silent mode and when snoozed', function () {
    Http::fake();

    $lead = Lead::create([
        'title' => 'Test Quant Lead',
        'lead_id' => 'TFA-L9001',
    ]);

    Task::create([
        'name' => 'Follow up overdue',
        'due_at' => Carbon::now()->subHours(5),
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
    ]);

    $tools = app(CrmToolsService::class);
    $heartbeat = app(CrmHeartbeatService::class);

    // 1. Silent mode
    $tools->updateNotificationSettings(['mode' => 'silent']);
    $silentResult = $heartbeat->runHeartbeat(false);

    expect($silentResult['status'])->toBe('suppressed_silent')
        ->and($silentResult['action_taken'])->toBeFalse();

    // 2. Balanced mode but snoozed for 3 hours
    $tools->updateNotificationSettings(['mode' => 'balanced', 'snooze_hours' => 3]);
    $snoozedResult = $heartbeat->runHeartbeat(false);

    expect($snoozedResult['status'])->toBe('snoozed')
        ->and($snoozedResult['action_taken'])->toBeFalse();

    Http::assertNothingSent();
});

it('enforces cooldown and smart delta deduplication in balanced mode', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Executive Briefing: 1 overdue task.'],
                        ],
                    ],
                ],
            ],
        ], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
    ]);

    $lead = Lead::create([
        'title' => 'Delta Hedge Fund',
        'lead_id' => 'TFA-L9002',
    ]);

    Task::create([
        'name' => 'Initial outreach',
        'due_at' => Carbon::now()->subHours(3),
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
    ]);

    $tools = app(CrmToolsService::class);
    $heartbeat = app(CrmHeartbeatService::class);

    $tools->updateNotificationSettings(['mode' => 'balanced', 'clear_snooze' => true]);

    // First heartbeat dispatches
    $first = $heartbeat->runHeartbeat(false);
    expect($first['status'])->toBe('dispatched')
        ->and($first['action_taken'])->toBeTrue();

    // Immediate second heartbeat is blocked by 4h cooldown
    $second = $heartbeat->runHeartbeat(false);
    expect($second['status'])->toBe('cooldown')
        ->and($second['action_taken'])->toBeFalse();

    // Fast-forward last_notified_at past the 4h cooldown, with identical alerts -> suppressed by no_delta
    $tools->saveNotificationState([
        'last_notified_at' => Carbon::now()->subHours(5)->toIso8601String(),
    ]);

    $third = $heartbeat->runHeartbeat(false);
    expect($third['status'])->toBe('no_delta')
        ->and($third['action_taken'])->toBeFalse();

    // Add a new overdue task -> delta changes -> dispatches!
    Task::create([
        'name' => 'Second urgent follow-up',
        'due_at' => Carbon::now()->subHour(),
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
    ]);

    $fourth = $heartbeat->runHeartbeat(false);
    expect($fourth['status'])->toBe('dispatched')
        ->and($fourth['action_taken'])->toBeTrue();
});

it('handles /mode command and inline mode/snooze button callbacks in Telegram webhook', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
    ]);

    $controller = app(AgentTelegramWebhookController::class);
    $telegram = app(TelegramBotService::class);
    $gemini = app(GeminiAgentService::class);
    $heartbeat = app(CrmHeartbeatService::class);
    $tools = app(CrmToolsService::class);

    // 1. Send /mode command
    $modeReq = Request::create('/telegram/webhook', 'POST', [
        'message' => [
            'chat' => ['id' => 123456789],
            'text' => '/mode',
        ],
    ]);

    $res = $controller->handle($modeReq, $telegram, $gemini, $heartbeat, $tools);
    expect($res->getStatusCode())->toBe(200);

    // 2. Click inline button to switch to Daily Digest (agent_mode:low)
    $callbackReq = Request::create('/telegram/webhook', 'POST', [
        'callback_query' => [
            'id' => 'cb-101',
            'data' => 'agent_mode:low',
            'message' => [
                'message_id' => 555,
                'chat' => ['id' => 123456789],
            ],
        ],
    ]);

    $controller->handle($callbackReq, $telegram, $gemini, $heartbeat, $tools);
    expect($tools->getNotificationSettings()['current_mode'])->toBe('low');

    // 3. Click inline button to snooze for 2 hours (agent_snooze:2)
    $snoozeCallbackReq = Request::create('/telegram/webhook', 'POST', [
        'callback_query' => [
            'id' => 'cb-102',
            'data' => 'agent_snooze:2',
            'message' => [
                'message_id' => 555,
                'chat' => ['id' => 123456789],
            ],
        ],
    ]);

    $controller->handle($snoozeCallbackReq, $telegram, $gemini, $heartbeat, $tools);
    expect($tools->getNotificationSettings()['is_snoozed'])->toBeTrue();
});
