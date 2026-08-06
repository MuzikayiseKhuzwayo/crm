<?php

namespace VentureDrake\LaravelCrm\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Scopes\BelongsToTeamsScope;

class SettingService
{
    protected string $cacheKey = 'app.crm-settings';

    protected int $ttl = 3600; // 1 hour (adjust)

    /**
     * Memoised answer to "does crm_settings have a user_id column?".
     *
     * Null until first asked. The service is a singleton, so this costs one
     * information_schema query per request rather than one per read.
     */
    protected ?bool $hasUserColumn = null;

    /**
     * The global settings map, keyed by name.
     *
     * Scoped to rows with a null user_id so a per-user row can never shadow the
     * global value of the same name — pluck() keys by name, so without this an
     * arbitrary user's row would win for every reader of the cached map.
     *
     * The hasUserColumn() check lives inside the closure so information_schema
     * is queried at most once per TTL rather than on every request, and so
     * hosts that never ran add_user_to_laravel_crm_settings_table still boot.
     */
    public function all(): array
    {
        return Cache::remember($this->cacheKey, $this->ttl, function () {
            return Setting::query()
                ->when(
                    $this->hasUserColumn(),
                    fn ($query) => $query->whereNull('user_id')
                )
                ->pluck('value', 'name')
                ->toArray();
        });
    }

    public function get(string $name, $default = null)
    {
        return Arr::get($this->all(), $name, $default);
    }

    public function first(string $name)
    {
        return Setting::where('name', $name)->first();
    }

    public function set($name, $value, $label = null)
    {
        return Setting::updateOrCreate([
            'name' => $name,
        ], [
            'value' => $value,
            'label' => $label,
        ]);
    }

    /**
     * Read a setting scoped to a single user.
     *
     * Deliberately a direct query rather than a cache read: the cached map in
     * all() holds global rows only, and per-user rows are written and read back
     * within the same request (a dismissal, for example), so a cached value
     * would be stale the moment it mattered.
     *
     * Setting's BelongsToTeams global scope means this is team-scoped for free.
     *
     * Guarded on hasUserColumn() for the same reason all() is: a host that has
     * not run add_user_to_laravel_crm_settings_table is exactly the behind-schema
     * install the system check reports on, so this must degrade rather than
     * throw on the way to rendering that report.
     */
    public function getForUser($userId, string $name, $default = null)
    {
        if (! $this->hasUserColumn()) {
            return $default;
        }

        $setting = Setting::query()
            ->where('user_id', $userId)
            ->where('name', $name)
            ->first();

        // Distinguish "no row" from "row holding a null value" — value is
        // nullable, so ?? would collapse the two.
        return $setting ? $setting->value : $default;
    }

    /**
     * Write a setting scoped to a single user, keyed on user_id plus name.
     *
     * Setting guards only `id`, so user_id mass-assigns without a fillable
     * change, and BelongsToTeams stamps team_id on creation.
     *
     * Returns null when the column is missing — the caller loses the write, but
     * a dismissal that cannot be stored is better than a fatal on every page.
     */
    public function setForUser($userId, string $name, $value)
    {
        if (! $this->hasUserColumn()) {
            return null;
        }

        return Setting::updateOrCreate([
            'user_id' => $userId,
            'name' => $name,
        ], [
            'value' => $value,
        ]);
    }

    /**
     * Write an install-wide setting — one that describes the schema or the
     * release rather than anything a team owns.
     *
     * The team scope is dropped deliberately. These rows are written from the
     * console (`laravelcrm:install`, `laravelcrm:update`), where there is no
     * authenticated user and so no team to stamp, but read back from web
     * requests where BelongsToTeamsScope pins every Setting query to the
     * current team. A plain set() therefore writes a row the reader cannot see.
     *
     * Every matching row is updated, not just the first, so per-team duplicates
     * left behind by older versions of this package collapse to one value
     * instead of keeping the alert alive forever.
     */
    public function setInstallWide(string $name, $value)
    {
        $rows = Setting::query()
            ->withoutGlobalScope(BelongsToTeamsScope::class)
            ->where('name', $name)
            ->get();

        if ($rows->isEmpty()) {
            return Setting::create([
                'name' => $name,
                'global' => 1,
                'value' => $value,
            ]);
        }

        // Saved model by model rather than through a builder update() so the
        // observer fires and both caches are dropped.
        foreach ($rows as $row) {
            $row->value = $value;
            $row->save();
        }

        return $rows->first();
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey);
    }

    /**
     * Whether crm_settings carries the user_id column, resolved once per
     * instance. The service is bound as a singleton, so this is once per
     * request rather than once per read.
     */
    protected function hasUserColumn(): bool
    {
        return $this->hasUserColumn ??= Schema::hasColumn((new Setting)->getTable(), 'user_id');
    }
}
