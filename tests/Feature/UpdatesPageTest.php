<?php

use Illuminate\Testing\TestResponse;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Services\SystemCheckService;

/**
 * Rendering contract for resources/views/updates/index.blade.php.
 *
 * Route-level authorization for this page lives in RouteAuthorizationTest; this file
 * covers what the page says once you are through the gate — specifically the version
 * comparison, which used string operators and so reported 2.2.0 as newer than 2.10.0.
 */
function updatesPagePath(): string
{
    return __DIR__.'/../../resources/views/updates/index.blade.php';
}

/**
 * Seed the rows UpdateController@index reads so rendering stays offline.
 *
 * install_id suppresses the POST to api.laravelcrm.com. The config version matters
 * because index() runs updateOrCreate on the version row from it, and it is null in
 * tests — seeding the row with the same value keeps that write non-dirty, so the
 * settings cache is not busted underneath us.
 */
function renderUpdatesPage(string $current, string $latest): TestResponse
{
    config(['laravel-crm.version' => $current]);

    Setting::create(['name' => 'install_id', 'value' => 'updates-page-test']);
    Setting::create(['name' => 'version', 'value' => $current]);
    Setting::create(['name' => 'version_latest', 'value' => $latest]);

    app('laravel-crm.settings')->forgetCache();

    test()->actingAsUserWithPermissions(['view crm updates']);

    return test()->get(route('laravel-crm.updates.index'));
}

/* -------------------------------------------------------------------------
 | The version comparison defect
 | ------------------------------------------------------------------------- */

it('reports an update available when the minor version is double-digit', function () {
    // The regression pin. Compared as strings '2.2.0' >= '2.10.0' is true, because '2'
    // beats '1' at the third character — so the page claimed you were up to date.
    $response = renderUpdatesPage('2.2.0', '2.10.0');

    $response->assertOk()
        ->assertSee('Updated version of Laravel CRM is available')
        ->assertSee('You can update from Laravel CRM')
        ->assertDontSee('is the latest version');
});

it('reports up to date when the current version matches the latest', function () {
    $response = renderUpdatesPage('2.3.0', '2.3.0');

    $response->assertOk()
        ->assertSee('is the latest version')
        ->assertDontSee('Updated version of Laravel CRM is available');
});

it('reports up to date when the current version is ahead of the latest', function () {
    // A dev build running ahead of the published release must not be told to downgrade.
    $response = renderUpdatesPage('2.10.0', '2.2.0');

    $response->assertOk()
        ->assertSee('is the latest version')
        ->assertDontSee('Updated version of Laravel CRM is available');
});

it('reports an update available for an ordinary single-digit bump', function () {
    // The case the old string comparison happened to get right — kept so a fix that
    // over-corrects in the other direction is caught too.
    $response = renderUpdatesPage('2.2.0', '2.3.0');

    $response->assertOk()
        ->assertSee('Updated version of Laravel CRM is available')
        ->assertDontSee('is the latest version');
});

it('uses version_compare in both branches rather than string operators', function () {
    $source = file_get_contents(updatesPagePath());

    expect(substr_count($source, 'version_compare('))->toBe(2)
        ->and($source)->not->toContain('$currentVersion >= $latestVersion')
        ->and($source)->not->toContain('$currentVersion < $latestVersion');
});

/* -------------------------------------------------------------------------
 | Reading through SettingService
 | ------------------------------------------------------------------------- */

it('reads the versions through SettingService rather than querying Setting directly', function () {
    $source = file_get_contents(updatesPagePath());

    expect($source)->toContain("app('laravel-crm.settings')")
        ->and($source)->not->toContain('Setting::where');
});

it('serves the versions from the shared settings cache', function () {
    // Behavioural proof that the page goes through SettingService: warm the cache, then
    // change the row without waking SettingObserver. A direct Setting::where would see
    // the new value; a cached read through the service still shows the old one.
    $response = renderUpdatesPage('2.2.0', '2.10.0');
    $response->assertOk()->assertSee('Updated version of Laravel CRM is available');

    Setting::withoutEvents(function () {
        Setting::where('name', 'version_latest')->update(['value' => '2.2.0']);
    });

    test()->get(route('laravel-crm.updates.index'))
        ->assertOk()
        ->assertSee('Updated version of Laravel CRM is available');
});

/* -------------------------------------------------------------------------
 | Docs link
 | ------------------------------------------------------------------------- */

it('points the upgrade guide button at the configured docs_url', function () {
    config(['laravel-crm.docs_url' => 'https://docs.example.test/upgrading']);

    renderUpdatesPage('2.2.0', '2.10.0')
        ->assertOk()
        ->assertSee('https://docs.example.test/upgrading')
        ->assertDontSee('https://github.com/venturedrake/laravel-crm');
});

it('does not hard-code the docs URL in the view', function () {
    $source = file_get_contents(updatesPagePath());

    expect($source)->toContain("config('laravel-crm.docs_url')")
        ->and($source)->not->toContain('https://github.com/venturedrake/laravel-crm');
});

/* -------------------------------------------------------------------------
 | The commands
 |
 | The page reported current-vs-latest and linked to the docs, but never said
 | what to actually run — so the only way to find out that laravelcrm:update
 | exists was to already know.
 | ------------------------------------------------------------------------- */

it('prints both update commands', function () {
    renderUpdatesPage('2.2.0', '2.10.0')
        ->assertOk()
        ->assertSee('composer update venturedrake/laravel-crm')
        ->assertSee('php artisan laravelcrm:update');
});

it('prints the commands even when the install is already on the latest release', function () {
    // A host can be on the latest *code* and still have a database behind it —
    // that is the whole reason db_version exists.
    renderUpdatesPage('2.3.0', '2.3.0')
        ->assertOk()
        ->assertSee('php artisan laravelcrm:update');
});

it('flags a database that is behind the installed code', function () {
    // db_version is absent here, which is the state every host that has never
    // run the current update command is in.
    renderUpdatesPage('2.3.0', '2.3.0')
        ->assertOk()
        ->assertSee('Database update required')
        ->assertSee('Your database is behind the installed code');
});

it('does not flag the database when it is level with the code', function () {
    // Both signals cleared: the version marker, and the db_update flags the
    // Settings middleware seeds pending on the way into this very request.
    Setting::create([
        'name' => SystemCheckService::DB_VERSION_SETTING,
        'value' => '2.3.0',
    ]);

    foreach (array_keys(SystemCheckService::DB_UPDATES) as $flag) {
        app('laravel-crm.settings')->setInstallWide($flag, 1);
    }

    renderUpdatesPage('2.3.0', '2.3.0')
        ->assertOk()
        ->assertDontSee('Database update required')
        // The commands themselves stay on the page regardless.
        ->assertSee('php artisan laravelcrm:update');
});
