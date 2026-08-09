<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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

/**
 * Clear the db_version marker, putting the install back in the state every
 * host that predates the marker is in.
 */
function forgetDbVersion(): void
{
    Setting::where('name', SystemCheckService::DB_VERSION_SETTING)->delete();

    app('laravel-crm.settings')->forgetCache();
}

/**
 * Where the package ships its own, loadMigrationsFrom-delivered migrations.
 */
function packageMigrationsPath(): string
{
    return dirname(__DIR__, 3).'/database/updates';
}

/**
 * Run a callback with database_path() pointed at an empty scratch directory,
 * and the migration repository standing.
 *
 * Both are needed to make the pending-migration signal deterministic. The
 * repository has to exist at all — hasPendingMigrations() short-circuits on
 * repositoryExists(), because a host that has never migrated is the
 * UPGRADE_REQUIRED case instead. And the real database_path('migrations') under
 * testbench accumulates published CRM stubs from whichever suites have run
 * before this one, so a test that asserted against it would pass or fail on the
 * order of the run.
 *
 * The callback receives the scratch migrations directory.
 */
function withScratchDatabasePath(callable $callback): void
{
    $scratch = sys_get_temp_dir().'/crm-database-path-'.uniqid();
    $migrations = $scratch.'/migrations';

    mkdir($migrations, 0777, true);

    $original = app()->databasePath();
    app()->useDatabasePath($scratch);

    app('migrator')->getRepository()->createRepository();

    // Mark the package's own shipped migrations as already run. The suite
    // builds its schema from TestSchema rather than by migrating, so every
    // file in database/updates is genuinely pending — which is a true signal,
    // and exactly the one the cases below are trying to hold still while they
    // vary something else. Logged before the callback, so a file the callback
    // writes is still seen as pending.
    foreach (glob(packageMigrationsPath().'/*.php') ?: [] as $migration) {
        app('migrator')->getRepository()->log(basename($migration, '.php'), 1);
    }

    try {
        $callback($migrations);
    } finally {
        app()->useDatabasePath($original);
        Schema::dropIfExists('migrations');
        File::deleteDirectory($scratch);
    }
}

beforeEach(function () {
    Cache::forget(SystemCheckService::CACHE_KEY);
    app('laravel-crm.settings')->forgetCache();
    config(['laravel-crm.update_notifications' => true]);

    // Every case below other than the db_version ones was written to exercise a
    // single signal, so stamp the marker level with the code and leave the
    // db_update flags as the variable. The cases that exercise the marker move
    // or clear it explicitly.
    seedSystemCheckSetting(SystemCheckService::DB_VERSION_SETTING, config('laravel-crm.version'));
});

test('exposes the cache key and ttl constants', function () {
    expect(SystemCheckService::CACHE_KEY)->toBe('app.crm-system-check')
        ->and(SystemCheckService::CACHE_TTL)->toBe(300);
});

test('exposes the db_version setting name', function () {
    expect(SystemCheckService::DB_VERSION_SETTING)->toBe('db_version');
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

/* -------------------------------------------------------------------------
 | The db_version marker
 |
 | The `version` setting cannot carry this: Http/Middleware/Settings overwrites
 | it with config('laravel-crm.version') on the first web request after a
 | deploy, so it always reads as current no matter what the database has had
 | applied.
 | ------------------------------------------------------------------------- */

test('a missing db_version marker triggers db_update_required', function () {
    // The state every host that predates the marker is in: it has never run the
    // update command that stamps it, so it should be told to.
    forgetDbVersion();

    $alerts = systemCheckService()->check();

    expect(systemCheckAlertTypes($alerts))->toContain(SystemCheckService::DB_UPDATE_REQUIRED);

    $alert = collect($alerts)->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

    expect($alert['version_behind'])->toBeTrue()
        ->and($alert['updates'])->toBe([]);
});

test('a db_version behind the code version triggers db_update_required', function () {
    config(['laravel-crm.version' => '2.4.0']);
    seedSystemCheckSetting(SystemCheckService::DB_VERSION_SETTING, '2.3.0');

    $alert = collect(systemCheckService()->check())
        ->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

    expect($alert)->not->toBeNull()
        ->and($alert['version_behind'])->toBeTrue();
});

test('db_version compares numerically rather than as a string', function () {
    // '2.9.0' < '2.10.0' is false lexicographically — the same defect that
    // silently stopped the update banner once the minor hit double digits.
    config(['laravel-crm.version' => '2.10.0']);
    seedSystemCheckSetting(SystemCheckService::DB_VERSION_SETTING, '2.9.0');

    expect(systemCheckAlertTypes(systemCheckService()->check()))
        ->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

test('a db_version level with the code does not trigger db_update_required', function () {
    config(['laravel-crm.version' => '2.4.0']);
    seedSystemCheckSetting(SystemCheckService::DB_VERSION_SETTING, '2.4.0');

    expect(systemCheckAlertTypes(systemCheckService()->check()))
        ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

test('a db_version ahead of the code does not trigger db_update_required', function () {
    // A rollback to an older release. The database is ahead, not behind, and
    // there is nothing to run.
    config(['laravel-crm.version' => '2.3.0']);
    seedSystemCheckSetting(SystemCheckService::DB_VERSION_SETTING, '2.4.0');

    expect(systemCheckAlertTypes(systemCheckService()->check()))
        ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

test('db_version is read install-wide so a console-written marker is visible', function () {
    // setInstallWide is what laravelcrm:update and laravelcrm:install use: a
    // console command has no team, so the row it writes is invisible to a
    // team-scoped read and the check would report an update it just completed.
    forgetDbVersion();

    app('laravel-crm.settings')->setInstallWide(
        SystemCheckService::DB_VERSION_SETTING,
        config('laravel-crm.version')
    );
    app('laravel-crm.settings')->forgetCache();

    expect(systemCheckAlertTypes(systemCheckService()->check()))
        ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

test('a stale db_version and a pending flag both report in the same alert', function () {
    forgetDbVersion();
    seedSystemCheckSetting('db_update_1201', 0);

    $alert = collect(systemCheckService()->check())
        ->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

    expect($alert['updates'])->toBe(['db_update_1201'])
        ->and($alert['version_behind'])->toBeTrue();
});

/* -------------------------------------------------------------------------
 | Pending migrations
 |
 | The signal that needs nobody to remember to register anything — the reason
 | DB_UPDATES is no longer load-bearing for detection.
 | ------------------------------------------------------------------------- */

test('the alert reports whether migrations are pending', function () {
    $alerts = systemCheckService()->check();

    // Nothing pending on a healthy install, and no alert raised on its account.
    expect(systemCheckAlertTypes($alerts))->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);

    forgetDbVersion();

    $alert = collect(systemCheckService()->check())
        ->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

    expect($alert)->toHaveKey('migrations_pending')
        ->and($alert['migrations_pending'])->toBeBool();
});

test('a database with no migrations table does not report pending migrations', function () {
    // repositoryExists() is false here — a host that has never migrated at all
    // is the UPGRADE_REQUIRED case, not this one, and introspecting it must not
    // throw on the way to rendering a banner.
    expect(Schema::hasTable('migrations'))->toBeFalse();

    $alerts = systemCheckService()->check();

    expect(systemCheckAlertTypes($alerts))->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

test('a published stub that has not run triggers db_update_required', function () {
    // The state a host is in after `composer update` republished the stubs and
    // before `laravelcrm:update` ran them. `create_laravel_crm_tables` is a real
    // entry in the frozen stub set, so it matches back to this package.
    withScratchDatabasePath(function (string $migrations) {
        file_put_contents($migrations.'/2026_01_01_000000_create_laravel_crm_tables.php', '<?php');

        $alert = collect(systemCheckService()->check())
            ->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

        expect($alert)->not->toBeNull()
            ->and($alert['migrations_pending'])->toBeTrue()
            // The other two signals are clean — this alert is the migration's doing.
            ->and($alert['updates'])->toBe([])
            ->and($alert['version_behind'])->toBeFalse();
    });
});

test('a migration shipped in database/updates triggers db_update_required', function () {
    // The delivery route every migration added from 2.4.0 on takes: a real .php
    // file inside the package, reaching the host through loadMigrationsFrom.
    $path = packageMigrationsPath().'/2099_01_01_000000_a_pending_crm_migration.php';

    withScratchDatabasePath(function () use ($path) {
        file_put_contents($path, '<?php');

        try {
            $alert = collect(systemCheckService()->check())
                ->firstWhere('type', SystemCheckService::DB_UPDATE_REQUIRED);

            expect($alert)->not->toBeNull()
                ->and($alert['migrations_pending'])->toBeTrue();
        } finally {
            unlink($path);
        }
    });
});

test('a pending migration belonging to the host application is not a CRM update', function () {
    // The regression this guards: scoping the check to "every path the migrator
    // knows about" made someone else's unrun migration raise a CRM banner — one
    // that tells the operator to run laravelcrm:update, which would have
    // migrated it for them.
    withScratchDatabasePath(function (string $migrations) {
        file_put_contents($migrations.'/2026_01_01_000000_add_widgets_to_orders_table.php', '<?php');

        expect(systemCheckAlertTypes(systemCheckService()->check()))
            ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
    });
});

test('a pending migration registered by another package is not a CRM update', function () {
    // Same regression, from the other direction: `$migrator->paths()` holds the
    // path every package registered via loadMigrationsFrom, not just ours.
    $path = sys_get_temp_dir().'/crm-other-package-migrations-'.uniqid();
    mkdir($path);
    file_put_contents($path.'/2026_01_01_000000_create_someone_elses_table.php', '<?php');

    app('migrator')->path($path);

    withScratchDatabasePath(function () {
        expect(systemCheckAlertTypes(systemCheckService()->check()))
            ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
    });

    unlink($path.'/2026_01_01_000000_create_someone_elses_table.php');
    rmdir($path);
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
