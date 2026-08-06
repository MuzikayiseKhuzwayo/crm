<?php

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Middleware\Settings as SettingsMiddleware;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Services\SystemCheckService;

/**
 * Run the Settings middleware the way a request would.
 *
 * install_id is seeded first so the version-phone-home block short-circuits —
 * without it the middleware would attempt a real HTTP call to
 * api.laravelcrm.com on every test.
 */
function runSettingsMiddleware(): void
{
    Setting::firstOrCreate(['name' => 'install_id'], ['value' => 'test-install']);

    (new SettingsMiddleware)->handle(Request::create('/'), fn ($request) => $request);

    app('laravel-crm.settings')->forgetCache();
}

function dbUpdateRows(): array
{
    return Setting::query()
        ->where('name', 'like', 'db_update_%')
        ->get()
        ->all();
}

function dbUpdateRow(string $flag): ?Setting
{
    return Setting::query()->where('name', $flag)->first();
}

beforeEach(function () {
    // Unset in the package config, so normalisedVersion() would otherwise be 0
    // and no flag would ever reach its minimum.
    config(['laravel-crm.version' => '2.3.0']);
});

// -----------------------------------------------------------------------
// Seeding the full flag set
// -----------------------------------------------------------------------

test('seeds a pending row for every db_update flag the version has reached', function () {
    runSettingsMiddleware();

    foreach (array_keys(SystemCheckService::DB_UPDATES) as $flag) {
        $row = dbUpdateRow($flag);

        expect($row)->not->toBeNull("Expected {$flag} to be seeded.")
            ->and((int) $row->value)->toBe(0, "Expected {$flag} to be seeded pending.");
    }

    expect(dbUpdateRows())->toHaveCount(count(SystemCheckService::DB_UPDATES));
});

test('seeds db_update_1201, which nothing created before', function () {
    runSettingsMiddleware();

    expect(dbUpdateRow('db_update_1201'))->not->toBeNull();
});

test('seeded rows are marked global so the team scope treats them as install-wide', function () {
    runSettingsMiddleware();

    foreach (array_keys(SystemCheckService::DB_UPDATES) as $flag) {
        expect((int) dbUpdateRow($flag)->global)->toBe(1, "Expected {$flag} to be global.");
    }
});

// -----------------------------------------------------------------------
// Version gating
// -----------------------------------------------------------------------

test('does not seed flags whose minimum version the install has not reached', function () {
    // 0.19.9 normalises to 199 — past every 01xx flag, short of both 12xx ones.
    config(['laravel-crm.version' => '0.19.9']);

    runSettingsMiddleware();

    foreach (['db_update_0180', 'db_update_0181', 'db_update_0191', 'db_update_0193', 'db_update_0194', 'db_update_0199'] as $flag) {
        expect(dbUpdateRow($flag))->not->toBeNull("Expected {$flag} at version 0.19.9.");
    }

    expect(dbUpdateRow('db_update_1200'))->toBeNull()
        ->and(dbUpdateRow('db_update_1201'))->toBeNull();
});

test('seeds nothing when the version is unset', function () {
    config(['laravel-crm.version' => null]);

    runSettingsMiddleware();

    expect(dbUpdateRows())->toHaveCount(0);
});

// -----------------------------------------------------------------------
// Idempotency: existing rows are adopted, never duplicated or reset
// -----------------------------------------------------------------------

test('leaves a completed flag at 1', function () {
    app('laravel-crm.settings')->set('db_update_1201', 1);

    runSettingsMiddleware();

    expect((int) dbUpdateRow('db_update_1201')->value)->toBe(1);
});

test('does not duplicate rows across repeated requests', function () {
    runSettingsMiddleware();
    runSettingsMiddleware();
    runSettingsMiddleware();

    expect(dbUpdateRows())->toHaveCount(count(SystemCheckService::DB_UPDATES));
});

test('adopts a row written without the global flag instead of duplicating it', function () {
    // Exactly what laravelcrm:install and laravelcrm:update write: SettingService
    // has no notion of `global`, so before this story the middleware's
    // global-keyed lookup missed the row and seeded a second copy at 0.
    Setting::withoutEvents(function () {
        Setting::create(['name' => 'db_update_1200', 'value' => 1, 'global' => 0]);
    });

    runSettingsMiddleware();

    expect(Setting::query()->where('name', 'db_update_1200')->count())->toBe(1)
        ->and((int) dbUpdateRow('db_update_1200')->value)->toBe(1);
});

// -----------------------------------------------------------------------
// The end-to-end contract: a fresh install reports nothing pending
// -----------------------------------------------------------------------

test('a fresh install followed by a request reports no pending db updates', function () {
    // The loop laravelcrm:install runs once the schema is in place.
    $settingService = app('laravel-crm.settings');

    foreach (array_keys(SystemCheckService::DB_UPDATES) as $flag) {
        $settingService->set($flag, 1);
    }

    runSettingsMiddleware();

    foreach (array_keys(SystemCheckService::DB_UPDATES) as $flag) {
        expect((int) dbUpdateRow($flag)->value)->toBe(1, "Expected {$flag} to stay applied.");
    }

    $types = array_column(app(SystemCheckService::class)->check(), 'type');

    expect($types)->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

test('a host missing db_update_1201 gets it seeded pending while applied flags stay applied', function () {
    $settingService = app('laravel-crm.settings');

    // An existing install that ran every update up to 1200 but has never seen 1201.
    foreach (array_keys(SystemCheckService::DB_UPDATES) as $flag) {
        if ($flag !== 'db_update_1201') {
            $settingService->set($flag, 1);
        }
    }

    runSettingsMiddleware();

    expect((int) dbUpdateRow('db_update_1201')->value)->toBe(0)
        ->and((int) dbUpdateRow('db_update_1200')->value)->toBe(1);

    $alerts = app(SystemCheckService::class)->check();
    $dbAlert = collect($alerts)->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

    expect($dbAlert)->not->toBeNull()
        ->and($dbAlert['updates'])->toBe(['db_update_1201']);
});
