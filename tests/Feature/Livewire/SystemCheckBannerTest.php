<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\SystemCheckBanner;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Services\SystemCheckService;

/**
 * Seed a setting and drop both caches, so the next read sees it.
 */
function seedBannerSetting(string $name, $value): void
{
    app('laravel-crm.settings')->set($name, $value);
    app('laravel-crm.settings')->forgetCache();
    app('laravel-crm.system-check')->forgetCache();
}

/**
 * Put the install one minor behind, which is what produces the
 * UPDATE_AVAILABLE alert the banner renders.
 */
function seedPendingUpdate(): void
{
    seedBannerSetting('version', '2.3.0');
    seedBannerSetting('version_latest', '2.10.0');
}

/**
 * Drop one of the users columns the CRM patches on, so the upgrade-required
 * branch can be exercised against the real Schema facade.
 *
 * Named distinctly from SystemCheckServiceTest's equivalent: Pest's top-level
 * functions are global, so two files cannot declare the same name.
 */
function withoutBannerUsersColumn(string $column, callable $callback): void
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

function bannerLayoutContentSlot(): string
{
    $source = file_get_contents(__DIR__.'/../../../resources/views/layouts/app.blade.php');

    // strrpos, not strpos: the nav popover higher up the file has a
    // <x-slot:content> of its own, and the page content slot is the last one.
    $start = strrpos($source, '<x-slot:content>');
    $end = strpos($source, '</x-slot:content>', $start === false ? 0 : $start);

    expect($start)->not->toBeFalse()
        ->and($end)->not->toBeFalse();

    return substr($source, $start, $end - $start);
}

beforeEach(function () {
    config(['laravel-crm.update_notifications' => true]);

    Cache::forget(SystemCheckService::CACHE_KEY);
    app('laravel-crm.settings')->forgetCache();
    Setting::query()->delete();

    // Stamp the database level with the code, as laravelcrm:install and
    // laravelcrm:update do. Without it every case here starts out with a
    // db_update_required alert it did not ask for, and the ones that assert on
    // an empty banner have nothing to assert.
    seedBannerSetting(SystemCheckService::DB_VERSION_SETTING, config('laravel-crm.version'));
});

it('renders the banner inside the layout content slot behind the update_notifications check', function () {
    $slot = bannerLayoutContentSlot();

    // The tag must sit inside the config gate, not merely somewhere in the
    // same slot — otherwise a host with notifications off still pays for it.
    $gate = strpos($slot, "@if(config('laravel-crm.update_notifications'))");
    $tag = strpos($slot, '<livewire:crm-system-check />');
    $endif = strpos($slot, '@endif', $gate === false ? 0 : $gate);

    expect($gate)->not->toBeFalse()
        ->and($tag)->not->toBeFalse()
        ->and($endif)->not->toBeFalse()
        ->and($tag)->toBeGreaterThan($gate)
        ->and($tag)->toBeLessThan($endif);
});

it('registers the component as crm-system-check', function () {
    seedPendingUpdate();
    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test('crm-system-check')->assertOk();
});

it('renders nothing for a guest even with a pending update', function () {
    seedPendingUpdate();

    auth()->logout();

    Livewire::test(SystemCheckBanner::class)
        ->assertSet('alerts', [])
        ->assertSet('signature', null)
        ->assertDontSee('2.10.0');
});

it('renders nothing for a user without view crm updates even with a pending update', function () {
    seedPendingUpdate();

    $this->actingAsUserWithPermissions(['view crm leads']);

    Livewire::test(SystemCheckBanner::class)
        ->assertSet('alerts', [])
        ->assertSet('signature', null)
        ->assertDontSee('2.10.0');
});

it('renders the version details link for a permitted user when version_latest is ahead', function () {
    seedPendingUpdate();

    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)
        ->assertSee('View version 2.10.0 details')
        ->assertSee('alert-warning', false)
        ->assertSee('wire:click="dismiss"', false);
});

it('uses the info level for a db update alert', function () {
    seedBannerSetting('version', '2.3.0');
    seedBannerSetting('db_update_1201', 0);

    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)
        ->assertSee('alert-info', false)
        ->assertDontSee('alert-warning', false)
        ->assertSee('update database');
});

it('prints the command the operator has to run for a db update alert', function () {
    // The fix is a line typed into a terminal, and the person reading this
    // banner is the person who has to type it. Neither this banner nor the
    // updates page used to say what that line was.
    seedBannerSetting('version', '2.3.0');
    seedBannerSetting('db_update_1201', 0);

    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)
        ->assertSee('php artisan laravelcrm:update')
        ->assertSee('<code', false);
});

/**
 * The icon can only be asserted structurally: Mary resolves the name into an
 * inline SVG, so `o-information-circle` never appears in the rendered output.
 */
it('maps the alert level to the mary-alert icon and class', function () {
    $view = file_get_contents(__DIR__.'/../../../resources/views/livewire/system-check-banner.blade.php');

    expect($view)->toContain("\$warning ? 'o-exclamation-triangle' : 'o-information-circle'")
        ->and($view)->toContain("\$warning ? 'alert-warning' : 'alert-info'")
        ->and($view)->toContain("(\$alert['level'] ?? 'info') === 'warning'");
});

/**
 * Mary's Alert renders the default slot only when `title` is null, and its
 * own `dismissible` toggle is Alpine-only — it would hide the bar without
 * recording anything. Both are load-bearing choices, so pin them.
 */
it('drives the alert through the slot and its own dismiss action', function () {
    $view = file_get_contents(__DIR__.'/../../../resources/views/livewire/system-check-banner.blade.php');

    // Scope the title/dismissible checks to the opening <x-mary-alert> tag —
    // the dismiss button legitimately carries a `title` tooltip of its own.
    $open = strpos($view, '<x-mary-alert');
    $tag = substr($view, $open, strpos($view, '>', $open) - $open);

    expect($open)->not->toBeFalse()
        ->and($tag)->not->toContain('title')
        ->and($tag)->not->toContain('dismissible')
        ->and($tag)->not->toContain('wire:key')
        ->and($tag)->toContain('id="crm-system-check-')
        ->and($view)->toContain('mb-6 grid gap-3')
        ->and($view)->toContain('<x-slot:actions>')
        ->and($view)->toContain('o-x-mark')
        ->and($view)->toContain('wire:click="dismiss"');
});

it('renders the upgrade-required alert against the configured docs url', function () {
    config(['laravel-crm.docs_url' => 'https://docs.example.test/crm']);

    $this->actingAsUserWithPermissions(['view crm updates']);

    withoutBannerUsersColumn('current_crm_team_id', function () {
        app('laravel-crm.system-check')->forgetCache();

        Livewire::test(SystemCheckBanner::class)
            ->assertSee('alert-warning', false)
            ->assertSee('upgrade guide')
            ->assertSee('https://docs.example.test/crm', false)
            ->assertDontSee('alert-info', false);
    });
});

it('points version details at the docs url and update now at the updates page', function () {
    config(['laravel-crm.docs_url' => 'https://docs.example.test/crm']);

    seedPendingUpdate();
    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)
        ->assertSee('href="https://docs.example.test/crm"', false)
        ->assertSee('href="'.route('laravel-crm.updates.index').'"', false);
});

it('escapes an interpolated version so it cannot inject markup', function () {
    seedBannerSetting('version', '2.3.0');
    seedBannerSetting('version_latest', '9.9.9<script>alert(1)</script>');

    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('writes a crm_settings row for the dismissing user and stays gone on re-render', function () {
    seedPendingUpdate();

    $user = $this->actingAsUserWithPermissions(['view crm updates']);

    $signature = app('laravel-crm.system-check')->signature();

    expect($signature)->not->toBeNull();

    Livewire::test(SystemCheckBanner::class)
        ->assertSee('View version 2.10.0 details')
        ->call('dismiss')
        ->assertSet('alerts', [])
        ->assertSet('signature', null)
        ->assertDontSee('View version 2.10.0 details');

    $row = Setting::query()
        ->where('user_id', $user->getKey())
        ->where('name', SystemCheckBanner::DISMISS_SETTING)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->value)->toBe($signature);

    // A fresh mount re-resolves from scratch, so this proves the dismissal
    // is read back rather than merely held in component state.
    Livewire::test(SystemCheckBanner::class)
        ->assertSet('alerts', [])
        ->assertDontSee('View version 2.10.0 details');
});

it('brings the banner back when version_latest changes after a dismissal', function () {
    seedPendingUpdate();

    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)->call('dismiss');

    Livewire::test(SystemCheckBanner::class)->assertDontSee('View version 2.11.0 details');

    seedBannerSetting('version_latest', '2.11.0');
    app('laravel-crm.system-check')->forgetCache();

    Livewire::test(SystemCheckBanner::class)
        ->assertSee('View version 2.11.0 details');
});

it('keeps the dismissal per user so a second user still sees the banner', function () {
    seedPendingUpdate();

    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)->call('dismiss');

    $second = $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)
        ->assertSee('View version 2.10.0 details');

    expect(Setting::query()->where('user_id', $second->getKey())->count())->toBe(0);
});

it('renders nothing when update notifications are switched off', function () {
    seedPendingUpdate();

    $this->actingAsUserWithPermissions(['view crm updates']);

    config(['laravel-crm.update_notifications' => false]);
    app('laravel-crm.system-check')->forgetCache();

    Livewire::test(SystemCheckBanner::class)
        ->assertSet('alerts', [])
        ->assertDontSee('2.10.0');
});

it('refuses a dismiss from a user without view crm updates', function () {
    seedPendingUpdate();

    $this->actingAsUserWithPermissions(['view crm leads']);

    Livewire::test(SystemCheckBanner::class)
        ->call('dismiss')
        ->assertForbidden();

    expect(Setting::query()->where('name', SystemCheckBanner::DISMISS_SETTING)->count())->toBe(0);
});

/**
 * Livewire properties are client-writable, so a tampered `signature` must not
 * be what lands in the database — dismiss() recomputes it server-side.
 */
it('persists the recomputed signature rather than the one held on the component', function () {
    seedPendingUpdate();

    $user = $this->actingAsUserWithPermissions(['view crm updates']);

    $real = app('laravel-crm.system-check')->signature();

    Livewire::test(SystemCheckBanner::class)
        ->set('signature', 'tampered-value')
        ->call('dismiss');

    $stored = Setting::query()
        ->where('user_id', $user->getKey())
        ->where('name', SystemCheckBanner::DISMISS_SETTING)
        ->value('value');

    expect($stored)->toBe($real)
        ->and($stored)->not->toBe('tampered-value');
});

it('does not write a dismissal row when there is nothing to dismiss', function () {
    $this->actingAsUserWithPermissions(['view crm updates']);

    Livewire::test(SystemCheckBanner::class)
        ->assertSet('signature', null)
        ->call('dismiss');

    expect(Setting::query()->where('name', SystemCheckBanner::DISMISS_SETTING)->count())->toBe(0);
});
