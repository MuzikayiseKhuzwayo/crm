<?php

namespace VentureDrake\LaravelCrm\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\Setting;

class SettingService
{
    protected string $cacheKey = 'app.crm-settings';

    protected int $ttl = 3600; // 1 hour (adjust)

    /**
     * The global settings map, keyed by name.
     *
     * Scoped to rows with a null user_id so a per-user row can never shadow the
     * global value of the same name — pluck() keys by name, so without this an
     * arbitrary user's row would win for every reader of the cached map.
     *
     * The hasColumn() check lives inside the closure so information_schema is
     * queried at most once per TTL rather than on every request, and so hosts
     * that never ran add_user_to_laravel_crm_settings_table still boot.
     */
    public function all(): array
    {
        return Cache::remember($this->cacheKey, $this->ttl, function () {
            return Setting::query()
                ->when(
                    Schema::hasColumn((new Setting)->getTable(), 'user_id'),
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
     */
    public function getForUser($userId, string $name, $default = null)
    {
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
     */
    public function setForUser($userId, string $name, $value)
    {
        return Setting::updateOrCreate([
            'user_id' => $userId,
            'name' => $name,
        ], [
            'value' => $value,
        ]);
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey);
    }
}
