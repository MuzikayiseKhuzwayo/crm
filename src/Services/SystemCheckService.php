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
 *  - DB_UPDATE_REQUIRED  one or more `laravelcrm:update` migrations have been
 *                      seeded but never run.
 */
class SystemCheckService
{
    public const CACHE_KEY = 'app.crm-system-check';

    public const CACHE_TTL = 300; // 5 minutes

    public const UPGRADE_REQUIRED = 'upgrade_required';

    public const UPDATE_AVAILABLE = 'update_available';

    public const DB_UPDATE_REQUIRED = 'db_update_required';

    /**
     * Every db_update flag, mapped to the minimum normalised version that
     * introduces it. Mirrors the seeding guards in
     * Http/Middleware/Settings.php — keep the two in step when a new flag
     * is added.
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

        $pending = $this->pendingDbUpdates();

        if (count($pending) > 0) {
            $alerts[] = [
                'type' => self::DB_UPDATE_REQUIRED,
                'level' => 'info',
                'updates' => $pending,
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
