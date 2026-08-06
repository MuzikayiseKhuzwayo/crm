<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Services\SystemCheckService;

function systemCheckService(): SystemCheckService
{
    return app(SystemCheckService::class);
}

function seedSystemCheckSetting(string $name, $value): void
{
    app('laravel-crm.settings')->set($name, $value);
    app('laravel-crm.settings')->forgetCache();
}

/**
 * Change a setting without firing model events, so SettingObserver does not
 * bust the system-check cache. Needed to exercise the caching itself: since
 * US-003 every ordinary setting write invalidates that cache by design, so a
 * normal write can no longer leave a stale value in place to observe.
 */
function seedSystemCheckSettingQuietly(string $name, $value): void
{
    Setting::withoutEvents(function () use ($name, $value) {
        app('laravel-crm.settings')->set($name, $value);
    });

    app('laravel-crm.settings')->forgetCache();
}

function systemCheckAlertTypes(array $alerts): array
{
    return array_column($alerts, 'type');
}

/**
 * Temporarily drop one of the users columns the CRM patches on, so the
 * upgrade-required branch can be exercised against the real Schema facade.
 */
function withoutCrmUsersColumn(string $column, callable $callback): void
{
    Schema::table('users', function (Blueprint $table) use ($column) {
        $table->dropColumn($column);
    });

    try {
        $callback();
    } finally {
        Schema::table('users', function (Blueprint $table) use ($column) {
            $table->unsignedBigInteger($column)->nullable();
        });
    }
}

beforeEach(function () {
    Cache::forget(SystemCheckService::CACHE_KEY);
    app('laravel-crm.settings')->forgetCache();
    config(['laravel-crm.update_notifications' => true]);
});

test('exposes the cache key and ttl constants', function () {
    expect(SystemCheckService::CACHE_KEY)->toBe('app.crm-system-check')
        ->and(SystemCheckService::CACHE_TTL)->toBe(300);
});

test('exposes the three alert type constants', function () {
    expect(SystemCheckService::UPGRADE_REQUIRED)->toBe('upgrade_required')
        ->and(SystemCheckService::UPDATE_AVAILABLE)->toBe('update_available')
        ->and(SystemCheckService::DB_UPDATE_REQUIRED)->toBe('db_update_required');
});

test('DB_UPDATES covers all eight flags including db_update_1201', function () {
    expect(SystemCheckService::DB_UPDATES)->toBe([
        'db_update_0180' => 180,
        'db_update_0181' => 181,
        'db_update_0191' => 191,
        'db_update_0193' => 193,
        'db_update_0194' => 194,
        'db_update_0199' => 199,
        'db_update_1200' => 1200,
        'db_update_1201' => 1201,
    ]);
});

test('check returns no alerts on a healthy install with nothing pending', function () {
    expect(systemCheckService()->check())->toBe([]);
});

test('version 2.2.0 with version_latest 2.10.0 yields an update_available alert', function () {
    // Pins defect 3: '2.2.0' < '2.10.0' is false under a string compare, so
    // the old middleware stopped announcing updates past minor version 9.
    seedSystemCheckSetting('version', '2.2.0');
    seedSystemCheckSetting('version_latest', '2.10.0');

    $alerts = systemCheckService()->check();

    expect(systemCheckAlertTypes($alerts))->toContain(SystemCheckService::UPDATE_AVAILABLE);

    $alert = collect($alerts)->firstWhere('type', SystemCheckService::UPDATE_AVAILABLE);

    expect($alert['level'])->toBe('warning')
        ->and($alert['current_version'])->toBe('2.2.0')
        ->and($alert['latest_version'])->toBe('2.10.0');
});

test('no update_available alert when the installed version is current', function () {
    seedSystemCheckSetting('version', '2.10.0');
    seedSystemCheckSetting('version_latest', '2.10.0');

    expect(systemCheckAlertTypes(systemCheckService()->check()))
        ->not->toContain(SystemCheckService::UPDATE_AVAILABLE);
});

test('no update_available alert when the installed version is ahead', function () {
    seedSystemCheckSetting('version', '2.10.0');
    seedSystemCheckSetting('version_latest', '2.2.0');

    expect(systemCheckAlertTypes(systemCheckService()->check()))
        ->not->toContain(SystemCheckService::UPDATE_AVAILABLE);
});

test('db_update_1201 alone triggers db_update_required', function () {
    seedSystemCheckSetting('db_update_1201', 0);

    $alerts = systemCheckService()->check();

    expect(systemCheckAlertTypes($alerts))->toContain(SystemCheckService::DB_UPDATE_REQUIRED);

    $alert = collect($alerts)->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

    expect($alert['level'])->toBe('info')
        ->and($alert['updates'])->toBe(['db_update_1201']);
});

test('a missing db_update flag row does not trigger db_update_required', function () {
    // Nothing seeded at all — every flag is absent rather than zero.
    expect(systemCheckAlertTypes(systemCheckService()->check()))
        ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

test('a completed db_update flag does not trigger db_update_required', function () {
    seedSystemCheckSetting('db_update_1201', 1);

    expect(systemCheckAlertTypes(systemCheckService()->check()))
        ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

test('db_update_required lists every pending flag in DB_UPDATES order', function () {
    seedSystemCheckSetting('db_update_1200', 0);
    seedSystemCheckSetting('db_update_0180', 0);
    seedSystemCheckSetting('db_update_0191', 1);

    $alert = collect(systemCheckService()->check())
        ->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

    expect($alert['updates'])->toBe(['db_update_0180', 'db_update_1200']);
});

test('upgrade_required short-circuits so no update_available appears alongside it', function () {
    seedSystemCheckSetting('version', '2.2.0');
    seedSystemCheckSetting('version_latest', '2.10.0');
    seedSystemCheckSetting('db_update_1201', 0);

    withoutCrmUsersColumn('current_crm_team_id', function () {
        $alerts = systemCheckService()->check();

        expect($alerts)->toHaveCount(1)
            ->and($alerts[0]['type'])->toBe(SystemCheckService::UPGRADE_REQUIRED)
            ->and($alerts[0]['level'])->toBe('warning')
            ->and(systemCheckAlertTypes($alerts))
            ->not->toContain(SystemCheckService::UPDATE_AVAILABLE)
            ->and(systemCheckAlertTypes($alerts))
            ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
    });
});

test('alerts returns an empty array when update notifications are off', function () {
    config(['laravel-crm.update_notifications' => false]);

    seedSystemCheckSetting('db_update_1201', 0);

    expect(systemCheckService()->alerts())->toBe([])
        ->and(Cache::has(SystemCheckService::CACHE_KEY))->toBeFalse();
});

test('alerts caches the result and refreshes after forgetCache', function () {
    seedSystemCheckSetting('db_update_1201', 0);

    expect(systemCheckAlertTypes(systemCheckService()->alerts()))
        ->toContain(SystemCheckService::DB_UPDATE_REQUIRED)
        ->and(Cache::has(SystemCheckService::CACHE_KEY))->toBeTrue();

    // Clear the pending flag with a quiet write so the alert cache survives —
    // the stale cached value must still be served. An ordinary write would go
    // through SettingObserver and invalidate it (covered in
    // tests/Feature/Observers/SettingObserverCacheTest.php).
    seedSystemCheckSettingQuietly('db_update_1201', 1);

    expect(systemCheckAlertTypes(systemCheckService()->alerts()))
        ->toContain(SystemCheckService::DB_UPDATE_REQUIRED);

    systemCheckService()->forgetCache();

    expect(systemCheckService()->alerts())->toBe([]);
});

test('signature is a 32 character digest of the current alerts', function () {
    seedSystemCheckSetting('db_update_1201', 0);

    $signature = systemCheckService()->signature();

    expect($signature)->toBeString()
        ->and(strlen($signature))->toBe(32)
        ->and($signature)->toBe(substr(sha1(json_encode(systemCheckService()->alerts())), 0, 32));
});

test('signature is null when there are no alerts', function () {
    expect(systemCheckService()->signature())->toBeNull();
});

test('signature changes when the alerts change', function () {
    seedSystemCheckSetting('db_update_1201', 0);

    $before = systemCheckService()->signature();

    seedSystemCheckSetting('db_update_1200', 0);
    systemCheckService()->forgetCache();

    expect(systemCheckService()->signature())->not->toBe($before);
});

test('normalisedVersion matches the seeding rule in the Settings middleware', function () {
    $service = systemCheckService();

    expect($service->normalisedVersion('0.1.80'))->toBe(180)
        ->and($service->normalisedVersion('0.1.99'))->toBe(199)
        ->and($service->normalisedVersion('1.2.0'))->toBe(1200)
        ->and($service->normalisedVersion('2.3.0'))->toBe(2300);
});

test('normalisedVersion defaults to the configured package version', function () {
    config(['laravel-crm.version' => '1.2.0']);

    expect(systemCheckService()->normalisedVersion())->toBe(1200);
});

test('every DB_UPDATES flag is gated at or below the configured version', function () {
    // Guards against adding a flag whose minimum version the shipped release
    // has not reached yet — the middleware would never seed it.
    $installed = systemCheckService()->normalisedVersion();

    foreach (SystemCheckService::DB_UPDATES as $name => $minimum) {
        expect($minimum)->toBeLessThanOrEqual($installed, $name);
    }
});
