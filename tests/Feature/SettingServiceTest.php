<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\Setting;

test('set creates a new setting', function () {
    $setting = app('laravel-crm.settings')->set('lead_prefix', 'L', 'Lead Prefix');

    expect($setting)->toBeInstanceOf(Setting::class);
    $this->assertDatabaseHas('crm_settings', [
        'name' => 'lead_prefix',
        'value' => 'L',
        'label' => 'Lead Prefix',
    ]);
});

test('set updates an existing setting', function () {
    $service = app('laravel-crm.settings');
    $service->set('currency', 'USD');
    $service->set('currency', 'AUD');

    expect(Setting::where('name', 'currency')->count())->toBe(1);
    expect(Setting::where('name', 'currency')->first()->value)->toBe('AUD');
});

test('get returns default when setting missing', function () {
    $service = app('laravel-crm.settings');

    expect($service->get('does_not_exist', 'fallback'))->toBe('fallback');
    expect($service->get('does_not_exist'))->toBeNull();
});

test('all returns settings keyed by name', function () {
    $service = app('laravel-crm.settings');
    $service->set('a', '1');
    $service->set('b', '2');
    $service->forgetCache();

    $all = $service->all();

    expect($all['a'])->toBe('1');
    expect($all['b'])->toBe('2');
});

test('all is cached', function () {
    $service = app('laravel-crm.settings');
    $service->set('cached', 'first');
    $service->forgetCache();

    expect($service->get('cached'))->toBe('first');

    Setting::where('name', 'cached')->update(['value' => 'second']);

    // Cached value still returned
    expect($service->get('cached'))->toBe('first');

    $service->forgetCache();

    expect($service->get('cached'))->toBe('second');
});

test('first returns underlying model', function () {
    $service = app('laravel-crm.settings');
    $service->set('lookup', 'value');

    $found = $service->first('lookup');

    expect($found)->toBeInstanceOf(Setting::class);
    expect($found->value)->toBe('value');
});

test('forget cache removes cached entry', function () {
    $service = app('laravel-crm.settings');
    $service->set('x', 'y');
    $service->all();

    expect(Cache::has('app.crm-settings'))->toBeTrue();

    $service->forgetCache();

    expect(Cache::has('app.crm-settings'))->toBeFalse();
});

test('all excludes user scoped rows', function () {
    $service = app('laravel-crm.settings');
    $service->set('global_only', 'global');
    $service->setForUser(1, 'user_only', 'mine');
    $service->forgetCache();

    $all = $service->all();

    expect($all)->toHaveKey('global_only')
        ->and($all)->not->toHaveKey('user_only');
});

test('a user scoped row does not shadow the global value of the same name', function () {
    $service = app('laravel-crm.settings');

    // Global row first, then a user row of the same name. pluck() keys by name,
    // so without the whereNull the user row would overwrite the global one.
    $service->set('date_format', 'd/m/Y');
    $service->setForUser(1, 'date_format', 'm/d/Y');
    $service->forgetCache();

    expect($service->all()['date_format'])->toBe('d/m/Y')
        ->and($service->get('date_format'))->toBe('d/m/Y');
});

test('get for user reads back the value written by set for user', function () {
    $service = app('laravel-crm.settings');

    $service->setForUser(7, 'system_check_dismissed', 'abc123');

    expect($service->getForUser(7, 'system_check_dismissed'))->toBe('abc123');
});

test('set for user upserts on user id plus name', function () {
    $service = app('laravel-crm.settings');

    $service->setForUser(7, 'system_check_dismissed', 'first');
    $service->setForUser(7, 'system_check_dismissed', 'second');

    expect(Setting::where('user_id', 7)->where('name', 'system_check_dismissed')->count())->toBe(1)
        ->and($service->getForUser(7, 'system_check_dismissed'))->toBe('second');
});

test('set for user keeps different users independent', function () {
    $service = app('laravel-crm.settings');

    $service->setForUser(1, 'system_check_dismissed', 'user-one');
    $service->setForUser(2, 'system_check_dismissed', 'user-two');

    expect(Setting::where('name', 'system_check_dismissed')->count())->toBe(2)
        ->and($service->getForUser(1, 'system_check_dismissed'))->toBe('user-one')
        ->and($service->getForUser(2, 'system_check_dismissed'))->toBe('user-two');
});

test('get for user returns the default when no row exists', function () {
    $service = app('laravel-crm.settings');

    expect($service->getForUser(99, 'never_set', 'fallback'))->toBe('fallback')
        ->and($service->getForUser(99, 'never_set'))->toBeNull();
});

test('get for user does not fall back to the global row', function () {
    $service = app('laravel-crm.settings');
    $service->set('date_format', 'd/m/Y');

    expect($service->getForUser(1, 'date_format', 'fallback'))->toBe('fallback');
});

test('set for user does not collide with the global setter', function () {
    $service = app('laravel-crm.settings');

    $service->set('date_format', 'd/m/Y');
    $service->setForUser(1, 'date_format', 'm/d/Y');

    expect(Setting::whereNull('user_id')->where('name', 'date_format')->count())->toBe(1)
        ->and(Setting::where('user_id', 1)->where('name', 'date_format')->count())->toBe(1)
        ->and($service->first('date_format')->value)->toBe('d/m/Y');
});

test('all still works on a host that never ran the add_user migration', function () {
    $service = app('laravel-crm.settings');
    $service->set('date_format', 'd/m/Y');
    $service->forgetCache();

    $table = (new Setting)->getTable();

    Schema::table($table, fn (Blueprint $t) => $t->dropColumn('user_id'));

    try {
        expect(Schema::hasColumn($table, 'user_id'))->toBeFalse()
            ->and($service->all()['date_format'])->toBe('d/m/Y');
    } finally {
        Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable());
    }
});

test('get for user is a direct query rather than a cache read', function () {
    $service = app('laravel-crm.settings');
    $service->setForUser(1, 'live', 'before');

    // Warm the global cache; per-user reads must not be served from it.
    $service->all();

    Setting::where('user_id', 1)->where('name', 'live')->update(['value' => 'after']);

    expect($service->getForUser(1, 'live'))->toBe('after');
});
