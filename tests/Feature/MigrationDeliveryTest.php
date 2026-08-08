<?php

use Illuminate\Filesystem\Filesystem;
use VentureDrake\LaravelCrm\LaravelCrmServiceProvider;

/**
 * How migrations reach a host.
 *
 * Two mechanisms, deliberately: a frozen set of `.stub` files that have to be
 * published into the host's own database/migrations, and — for everything added
 * from 2.4.0 onwards — real `.php` files in database/updates that the provider
 * loads, so a plain `php artisan migrate` runs them with no publish step.
 *
 * The failure this file guards against is a silent one. A `.stub` with no entry
 * in the publish array can never reach any host, and nothing at runtime
 * complains about it.
 */
function packageRoot(): string
{
    return dirname(__DIR__, 2);
}

function serviceProviderSource(): string
{
    return file_get_contents(packageRoot().'/src/LaravelCrmServiceProvider.php');
}

/** Invoke the provider's protected filename minter. */
function mintMigrationFileName(string $filename, int $order): string
{
    $provider = new LaravelCrmServiceProvider(app());

    return (function () use ($filename, $order) {
        return $this->getMigrationFileName(new Filesystem, $filename, $order);
    })->call($provider);
}

// -----------------------------------------------------------------------
// The frozen stub set
// -----------------------------------------------------------------------

test('every migration stub has an entry in the publish array', function () {
    // A stub with no entry is dead weight that looks like a shipped migration.
    // create_audits_table.php.stub sat here for years in exactly that state.
    $source = serviceProviderSource();

    $orphans = collect((new Filesystem)->files(packageRoot().'/database/migrations'))
        ->map(fn ($file) => $file->getFilename())
        ->reject(fn (string $name) => str_contains($source, "database/migrations/{$name}'"))
        ->values()
        ->all();

    expect($orphans)->toBe([], 'Stub files with no publish entry: '.implode(', ', $orphans));
});

test('database/migrations holds only stubs', function () {
    // A real .php in here would be published *and* invisible to the migrator's
    // *_*.php glob at the same time — the worst of both delivery mechanisms.
    $stray = collect((new Filesystem)->files(packageRoot().'/database/migrations'))
        ->map(fn ($file) => $file->getFilename())
        ->reject(fn (string $name) => str_ends_with($name, '.php.stub'))
        ->values()
        ->all();

    expect($stray)->toBe([]);
});

test('each publish entry has a distinct order number', function () {
    // Two stubs sharing an order collide on the minted filename, so the second
    // silently overwrites the first on publish.
    preg_match_all(
        "/getMigrationFileName\(\\\$filesystem, '[^']+\.php', (\d+)\)/",
        serviceProviderSource(),
        $matches
    );

    $orders = $matches[1];

    expect($orders)->not->toBeEmpty()
        ->and(count(array_unique($orders)))->toBe(count($orders));
});

// -----------------------------------------------------------------------
// The loaded directory
// -----------------------------------------------------------------------

test('the provider loads migrations from database/updates', function () {
    // database/migrations holds .stub files, which the migrator's *_*.php glob
    // cannot see — loading that directory was inert.
    expect(serviceProviderSource())
        ->toContain("loadMigrationsFrom(__DIR__.'/../database/updates')")
        ->and(serviceProviderSource())
        ->not->toContain("loadMigrationsFrom(__DIR__.'/../database/migrations')");
});

test('the loaded directory exists and is registered with the migrator', function () {
    // Compared through realpath: loadMigrationsFrom registers the literal
    // __DIR__.'/../database/updates' string, dots and all.
    $registered = array_map('realpath', app('migrator')->paths());

    expect(is_dir(packageRoot().'/database/updates'))->toBeTrue()
        ->and($registered)->toContain(realpath(packageRoot().'/database/updates'));
});

test('the loaded directory contains no stubs', function () {
    $stubs = collect((new Filesystem)->files(packageRoot().'/database/updates'))
        ->map(fn ($file) => $file->getFilename())
        ->filter(fn (string $name) => str_contains($name, '.stub'))
        ->values()
        ->all();

    expect($stubs)->toBe([]);
});

test('every php file in the loaded directory is a runnable migration', function () {
    // The migrator globs *_*.php, so a .php without a timestamp prefix would be
    // ignored without a word.
    $bad = collect((new Filesystem)->files(packageRoot().'/database/updates'))
        ->map(fn ($file) => $file->getFilename())
        ->filter(fn (string $name) => str_ends_with($name, '.php'))
        ->reject(fn (string $name) => preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_\w+\.php$/', $name) === 1)
        ->values()
        ->all();

    expect($bad)->toBe([]);
});

// -----------------------------------------------------------------------
// Ordering
// -----------------------------------------------------------------------

test('published stubs are stamped from a fixed epoch, not the moment of publishing', function () {
    // now() meant a stub published today sorted *after* a package-loaded
    // migration authored earlier — so the migrator would try to alter tables
    // that did not exist yet.
    expect(serviceProviderSource())
        ->toContain("strtotime('2024-01-01 00:00:00')")
        ->and(serviceProviderSource())->not->toContain('strtotime("+$order sec")');
});

test('a freshly minted stub filename uses the fixed epoch', function () {
    $name = basename(mintMigrationFileName('a_migration_no_host_has_published.php', 1));

    expect($name)->toBe('2024_01_01_000001_a_migration_no_host_has_published.php');
});

test('the order number spaces stub filenames one second apart', function () {
    expect(basename(mintMigrationFileName('another_unpublished_migration.php', 141)))
        ->toBe('2024_01_01_000221_another_unpublished_migration.php');
});

test('every package-loaded migration sorts after every published stub', function () {
    // The whole point of the fixed epoch. Package-loaded migrations alter
    // tables the stubs create, so this ordering is a correctness requirement,
    // not a tidiness one.
    preg_match_all(
        "/getMigrationFileName\(\\\$filesystem, '[^']+\.php', (\d+)\)/",
        serviceProviderSource(),
        $matches
    );

    $latestStub = basename(mintMigrationFileName('z.php', max(array_map('intval', $matches[1]))));

    $tooEarly = collect((new Filesystem)->files(packageRoot().'/database/updates'))
        ->map(fn ($file) => $file->getFilename())
        ->filter(fn (string $name) => str_ends_with($name, '.php'))
        ->filter(fn (string $name) => strcmp($name, $latestStub) <= 0)
        ->values()
        ->all();

    expect($tooEarly)->toBe([], "These sort before the last published stub ({$latestStub}): ".implode(', ', $tooEarly));
});

test('the fixed epoch sorts after the host baseline a Laravel app ships with', function () {
    // Jetstream's teams table is 2020_05_21_*, and the stock users table is
    // older still. Both have to run before anything here.
    expect(basename(mintMigrationFileName('a_migration_no_host_has_published.php', 1)))
        ->toBeGreaterThan('2020_05_21_999999_create_teams_table.php');
});

test('an already-published migration keeps the filename the host gave it', function () {
    // Existing hosts published under the old now()-based names. Renaming them
    // would re-run migrations the migrations table already records.
    $published = database_path('migrations/2019_09_09_121212_an_already_published_migration.php');

    (new Filesystem)->ensureDirectoryExists(dirname($published));
    file_put_contents($published, '<?php');

    try {
        expect(mintMigrationFileName('an_already_published_migration.php', 1))->toBe($published);
    } finally {
        unlink($published);
    }
});
