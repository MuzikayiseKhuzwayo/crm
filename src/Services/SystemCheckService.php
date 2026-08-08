<?php

namespace VentureDrake\LaravelCrm\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Scopes\BelongsToTeamsScope;

/**
 * Collects the "your install needs attention" alerts that the (now dead)
 * SystemCheck middleware used to compute inline on every request.
 *
 * Three alert types are produced:
 *
 *  - UPGRADE_REQUIRED  the schema itself is behind, so nothing else can be
 *                      trusted. Short-circuits the rest of the check.
 *  - UPDATE_AVAILABLE  a newer release exists than the one installed.
 *  - DB_UPDATE_REQUIRED  the database is behind the code — `laravelcrm:update`
 *                      has not been run against this release.
 */
class SystemCheckService
{
    public const CACHE_KEY = 'app.crm-system-check';

    public const CACHE_TTL = 300; // 5 minutes

    public const UPGRADE_REQUIRED = 'upgrade_required';

    public const UPDATE_AVAILABLE = 'update_available';

    public const DB_UPDATE_REQUIRED = 'db_update_required';

    /**
     * The setting laravelcrm:update stamps with config('laravel-crm.version')
     * when it completes.
     *
     * Not the same row as `version`: Http/Middleware/Settings overwrites that
     * one with the config value on the first web request after a deploy, so it
     * reports the code's version and can never reveal that the database is
     * behind it.
     */
    public const DB_VERSION_SETTING = 'db_version';

    /**
     * Every db_update flag, mapped to the minimum normalised version that
     * introduces it. Mirrors the seeding guards in
     * Http/Middleware/Settings.php — keep the two in step when a new flag
     * is added.
     *
     * This list is the *worklist* of data backfills laravelcrm:update performs.
     * It is deliberately no longer the only thing detection depends on — see
     * dbVersionBehind() and hasPendingMigrations() — because a hand-maintained
     * list is exactly the thing that gets forgotten.
     *
     * @var array<string, int>
     */
    public const DB_UPDATES = [
        'db_update_0180' => 180,
        'db_update_0181' => 181,
        'db_update_0191' => 191,
        'db_update_0193' => 193,
        'db_update_0194' => 194,
        'db_update_0199' => 199,
        'db_update_1200' => 1200,
        'db_update_1201' => 1201,
    ];

    public function __construct(protected SettingService $settingService) {}

    /**
     * Run the checks and return the alerts they produce. Uncached — callers
     * that render on every request should use alerts() instead.
     *
     * @return array<int, array<string, mixed>>
     */
    public function check(): array
    {
        $alerts = [];

        // A missing settings table or a users table that predates the initial
        // release means the install is structurally behind. Every subsequent
        // check reads from one of those, so bail out rather than guess.
        if ($this->upgradeRequired()) {
            $alerts[] = [
                'type' => self::UPGRADE_REQUIRED,
                'level' => 'warning',
            ];

            return $alerts;
        }

        $currentVersion = $this->settingService->get('version');
        $latestVersion = $this->settingService->get('version_latest');

        // version_compare(), not a string compare: '2.2.0' < '2.10.0' is true
        // numerically but false lexicographically, so the old string compare
        // silently stopped announcing updates once the minor hit double digits.
        if ($currentVersion && $latestVersion && version_compare($currentVersion, $latestVersion, '<')) {
            $alerts[] = [
                'type' => self::UPDATE_AVAILABLE,
                'level' => 'warning',
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
            ];
        }

        // Three independent signals, because each on its own has a blind spot:
        // a db_update_* flag only exists if someone remembered to add it, the
        // db_version marker is absent on installs that predate it, and pending
        // migrations are invisible until the migration repository exists.
        $pending = $this->pendingDbUpdates();
        $versionBehind = $this->dbVersionBehind();
        $migrationsPending = $this->hasPendingMigrations();

        if (count($pending) > 0 || $versionBehind || $migrationsPending) {
            $alerts[] = [
                'type' => self::DB_UPDATE_REQUIRED,
                'level' => 'info',
                'updates' => $pending,
                'version_behind' => $versionBehind,
                'migrations_pending' => $migrationsPending,
            ];
        }

        return $alerts;
    }

    /**
     * The cached alerts. Empty when update notifications are switched off.
     *
     * @return array<int, array<string, mixed>>
     */
    public function alerts(): array
    {
        if (! config('laravel-crm.update_notifications')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->check();
        });
    }

    /**
     * A stable fingerprint of the current alerts, so a UI can tell whether
     * anything changed since the user last dismissed them. Null when there
     * is nothing to report.
     */
    public function signature(): ?string
    {
        $alerts = $this->alerts();

        if (count($alerts) === 0) {
            return null;
        }

        return substr(sha1(json_encode($alerts)), 0, 32);
    }

    /**
     * Normalise a dotted version into the integer form the db_update flags
     * are named after. Lifted verbatim from
     * Http/Middleware/Settings.php so the two cannot drift.
     */
    public function normalisedVersion(?string $version = null): int
    {
        $version ??= (string) config('laravel-crm.version');

        if (Str::startsWith($version, '0.')) {
            return (int) Str::replace('.', '', $version);
        }

        return (int) Str::replace('.', '', $version) * 10;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The db_update flags still sitting at 0, in DB_UPDATES order.
     *
     * Read straight from the table with the team scope dropped rather than
     * through the cached settings map. These flags describe the schema, which
     * is install-wide, but the map is team-scoped under `laravel-crm.teams` —
     * so a flag marked applied from the console (`laravelcrm:install`,
     * `laravelcrm:update`, neither of which has a team) is invisible to it, and
     * a fresh install reports database updates that have already been done.
     *
     * A flag counts as pending when *any* of its rows holds 0, so per-team
     * duplicates written by older versions of this package cannot mask a
     * genuinely outstanding update.
     *
     * @return array<int, string>
     */
    protected function pendingDbUpdates(): array
    {
        $rows = Setting::query()
            ->withoutGlobalScope(BelongsToTeamsScope::class)
            ->whereIn('name', array_keys(self::DB_UPDATES))
            ->get(['name', 'value']);

        $names = [];

        foreach ($rows as $row) {
            if ((int) $row->value === 0) {
                $names[$row->name] = true;
            }
        }

        // Rebuilt from DB_UPDATES rather than from row order so the list — and
        // therefore signature() — is stable across queries.
        return array_values(array_filter(
            array_keys(self::DB_UPDATES),
            fn (string $name) => isset($names[$name])
        ));
    }

    /**
     * True when laravelcrm:update has not been run against the installed code.
     *
     * Read install-wide, with the team scope dropped, for the same reason
     * pendingDbUpdates() is: the marker is written from a console command that
     * has no team, so a team-scoped read cannot see it.
     *
     * A missing marker counts as behind. Either the host predates the marker
     * (in which case it has never run the current update command and should),
     * or the database was never stamped — both want the same action.
     */
    protected function dbVersionBehind(): bool
    {
        $codeVersion = (string) config('laravel-crm.version');

        // No version to compare against — say nothing rather than nag.
        if ($codeVersion === '') {
            return false;
        }

        $rows = Setting::query()
            ->withoutGlobalScope(BelongsToTeamsScope::class)
            ->where('name', self::DB_VERSION_SETTING)
            ->get(['value']);

        if ($rows->isEmpty()) {
            return true;
        }

        // Any row behind means behind, so per-team duplicates written by older
        // versions of this package cannot mask an outstanding update.
        foreach ($rows as $row) {
            $dbVersion = (string) $row->value;

            if ($dbVersion === '' || version_compare($dbVersion, $codeVersion, '<')) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when this package has migration files that have not been run.
     *
     * This is the check that makes DB_UPDATES non-load-bearing for detection:
     * it needs nobody to remember to register anything, so a migration added to
     * database/updates announces itself.
     *
     * Scoped to this package's own migrations — see crmMigrationFiles(). The
     * host's migrations and other packages' live in the same directories and
     * in the same migrator paths, and an unrun one of those says nothing about
     * whether *this* package's database is behind.
     *
     * Guarded on repositoryExists() — a host without a migrations table has
     * never migrated at all, which is the UPGRADE_REQUIRED case above rather
     * than this one. Wrapped because this runs on a page render: an
     * unintrospectable connection must degrade to "nothing to report", not
     * throw on the way to rendering a banner.
     */
    protected function hasPendingMigrations(): bool
    {
        try {
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return false;
            }

            $files = $this->crmMigrationFiles($migrator);

            if ($files === []) {
                return false;
            }

            $ran = $migrator->getRepository()->getRan();

            return count(array_diff(array_keys($files), $ran)) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The migration files this package is responsible for, keyed by migration
     * name.
     *
     * Two sources, because the package delivers migrations two ways:
     *
     *  - real .php files in database/updates, loaded straight out of the
     *    package by loadMigrationsFrom;
     *  - the frozen .stub set, published into the host's database/migrations
     *    under the timestamped name getMigrationFileName() minted for it.
     *
     * Deliberately *not* every path the migrator knows about. `$migrator->
     * paths()` holds the paths registered by every package in the application,
     * and database_path('migrations') holds the host's own work alongside our
     * published stubs. Counting those would raise a "Laravel CRM needs a
     * database update" banner over someone else's unrun migration — and the
     * banner tells the operator to run laravelcrm:update, which would migrate
     * it for them.
     *
     * @return array<string, string>
     */
    protected function crmMigrationFiles($migrator): array
    {
        $files = $migrator->getMigrationFiles([$this->packageMigrationPath()]);

        $stubs = $this->publishedStubNames();

        if ($stubs === []) {
            return $files;
        }

        foreach ($migrator->getMigrationFiles([database_path('migrations')]) as $name => $path) {
            // A published stub keeps its source filename behind the timestamp:
            // 2024_01_01_000003_create_laravel_crm_tables.
            $source = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name);

            if (isset($stubs[$source])) {
                $files[$name] = $path;
            }
        }

        return $files;
    }

    /**
     * Where the package's own, loadMigrationsFrom-delivered migrations live.
     */
    protected function packageMigrationPath(): string
    {
        return __DIR__.'/../../database/updates';
    }

    /**
     * The base names of every .stub the package publishes, as a lookup.
     *
     * Read off disk rather than hard-coded so it cannot drift from the publish
     * array in LaravelCrmServiceProvider — which is frozen, but was frozen
     * with 137 entries already in it.
     *
     * @return array<string, true>
     */
    protected function publishedStubNames(): array
    {
        $names = [];

        foreach (glob(__DIR__.'/../../database/migrations/*.php.stub') ?: [] as $stub) {
            $names[basename($stub, '.php.stub')] = true;
        }

        return $names;
    }

    /**
     * True when the schema is behind the initial release — either the
     * settings table is missing entirely, or the users table never got the
     * columns the CRM patches onto it.
     */
    protected function upgradeRequired(): bool
    {
        if (! Schema::hasTable(config('laravel-crm.db_table_prefix').'settings')) {
            return true;
        }

        foreach (['crm_access', 'last_online_at', 'current_crm_team_id'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                return true;
            }
        }

        return false;
    }
}
