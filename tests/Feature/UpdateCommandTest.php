<?php

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use VentureDrake\LaravelCrm\Console\LaravelCrmUpdate;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Services\SystemCheckService;

/**
 * laravelcrm:update is the explicit, database-touching half of the upgrade —
 * the one that goes in a deploy script.
 *
 * It used to catch migration and seeder exceptions, downgrade them to warnings
 * and still print "Laravel CRM is now updated", so a broken upgrade and a clean
 * one produced the same deploy log and the same exit code. Most of what is
 * pinned below is that: failures are fatal, and the db_version stamp only moves
 * when the whole run succeeded.
 */

/**
 * Replace a command on the console application for the duration of a test.
 *
 * The real `migrate` would run testbench's own migration set against a schema
 * this suite builds by hand, and `laravelcrm:upgrade` would publish assets —
 * neither is what these cases are about, and both have their own coverage.
 */
function fakeArtisanCommand(string $signature, int $exitCode, ?string $throws = null): void
{
    $command = new class($signature, $exitCode, $throws) extends Command
    {
        public function __construct(string $signature, private int $exitCode, private ?string $throws)
        {
            $this->signature = $signature;

            parent::__construct();
        }

        public function handle(): int
        {
            if ($this->throws !== null) {
                throw new RuntimeException($this->throws);
            }

            return $this->exitCode;
        }
    };

    $command->setLaravel(app());

    app(Kernel::class)->registerCommand($command);
}

/** Stub out everything laravelcrm:update shells out to that needs a real schema. */
function fakeUpdateDependencies(int $migrateExitCode = Command::SUCCESS, ?string $migrateThrows = null): void
{
    fakeArtisanCommand('laravelcrm:upgrade', Command::SUCCESS);
    fakeArtisanCommand('migrate {--force} {--path=*} {--database=} {--seed} {--step} {--pretend} {--isolated}', $migrateExitCode, $migrateThrows);
    fakeArtisanCommand('db:seed {--class=} {--force} {--database=}', Command::SUCCESS);
}

function storedDbVersion(): ?string
{
    $value = Setting::where('name', SystemCheckService::DB_VERSION_SETTING)->value('value');

    return $value === null ? null : (string) $value;
}

// -----------------------------------------------------------------------
// Shape
// -----------------------------------------------------------------------

test('update command is registered', function () {
    expect(app(Kernel::class)->all())->toHaveKey('laravelcrm:update');
});

test('update command describes what it does', function () {
    // It used to read "Install Laravel CRM package", copied from the installer.
    $description = app(Kernel::class)->all()['laravelcrm:update']->getDescription();

    expect($description)->not->toBe('Install Laravel CRM package')
        ->and(strtolower($description))->toContain('migration');
});

test('update command accepts --force for deploy scripts', function () {
    expect(app(Kernel::class)->all()['laravelcrm:update']->getDefinition()->hasOption('force'))
        ->toBeTrue();
});

test('update exposes the db_version setting name the system check reads', function () {
    expect(LaravelCrmUpdate::DB_VERSION_SETTING)->toBe(SystemCheckService::DB_VERSION_SETTING);
});

// -----------------------------------------------------------------------
// Failures are fatal
// -----------------------------------------------------------------------

test('update fails loudly when migrate throws', function () {
    fakeUpdateDependencies(migrateThrows: 'SQLSTATE[42S02]: Base table or view not found');

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->expectsOutputToContain('Migrations failed')
        ->doesntExpectOutputToContain('Laravel CRM is now updated.')
        ->assertExitCode(Command::FAILURE);
});

test('update fails loudly when migrate exits non-zero', function () {
    // A migration that returns a failure code rather than raising still leaves
    // the schema behind the code.
    fakeUpdateDependencies(migrateExitCode: Command::FAILURE);

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->expectsOutputToContain('Migrations failed')
        ->doesntExpectOutputToContain('Laravel CRM is now updated.')
        ->assertExitCode(Command::FAILURE);
});

test('update fails loudly when the base seeder fails', function () {
    fakeArtisanCommand('laravelcrm:upgrade', Command::SUCCESS);
    fakeArtisanCommand('migrate {--force} {--path=*} {--database=} {--seed} {--step} {--pretend} {--isolated}', Command::SUCCESS);
    fakeArtisanCommand('db:seed {--class=} {--force} {--database=}', Command::FAILURE);

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->expectsOutputToContain('Seeding base tables failed')
        ->doesntExpectOutputToContain('Laravel CRM is now updated.')
        ->assertExitCode(Command::FAILURE);
});

test('update fails loudly when the upgrade half fails', function () {
    fakeArtisanCommand('laravelcrm:upgrade', Command::FAILURE);

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->expectsOutputToContain('laravelcrm:upgrade failed')
        ->assertExitCode(Command::FAILURE);
});

test('a failed run does not stamp db_version', function () {
    // The stamp is what silences the "your database is behind" alert, so
    // stamping after a partial run would hide the very thing that would have
    // told the operator to re-run this command.
    fakeUpdateDependencies(migrateThrows: 'boom');

    $this->artisan('laravelcrm:update', ['--force' => true])->assertExitCode(Command::FAILURE);

    expect(storedDbVersion())->toBeNull();
});

test('a failed migration stops before the seeder runs', function () {
    // Every backfill below reads the schema the migration was meant to change.
    fakeUpdateDependencies(migrateThrows: 'boom');

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->doesntExpectOutputToContain('Reseeding base tables')
        ->assertExitCode(Command::FAILURE);
});

// -----------------------------------------------------------------------
// The success path
// -----------------------------------------------------------------------

test('update succeeds and reports it', function () {
    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->expectsOutputToContain('Laravel CRM is now updated.')
        ->assertExitCode(Command::SUCCESS);
});

test('update stamps db_version with the installed package version', function () {
    config(['laravel-crm.version' => '2.4.0']);

    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])->assertExitCode(Command::SUCCESS);

    expect(storedDbVersion())->toBe('2.4.0');
});

test('update runs the upgrade half first', function () {
    // So an operator running one command by hand still gets the assets
    // republished and the caches cleared, and so a stale config:cache cannot
    // poison the config reads the backfills below depend on.
    $source = file_get_contents(
        (new ReflectionClass(LaravelCrmUpdate::class))->getFileName()
    );

    $upgrade = strpos($source, "\$this->call('laravelcrm:upgrade')");
    $migrate = strpos($source, "\$this->call('migrate'");

    expect($upgrade)->not->toBeFalse()
        ->and($migrate)->not->toBeFalse()
        ->and($upgrade)->toBeLessThan($migrate);
});

test('update clears the db_update backfill flags it has applied', function () {
    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])->assertExitCode(Command::SUCCESS);

    foreach (array_keys(SystemCheckService::DB_UPDATES) as $flag) {
        expect((int) Setting::where('name', $flag)->value('value'))
            ->toBe(1, "Expected {$flag} to be marked applied.");
    }
});

test('a completed update leaves nothing for the system check to report', function () {
    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])->assertExitCode(Command::SUCCESS);

    app('laravel-crm.settings')->forgetCache();
    app('laravel-crm.system-check')->forgetCache();

    expect(array_column(app(SystemCheckService::class)->check(), 'type'))
        ->not->toContain(SystemCheckService::DB_UPDATE_REQUIRED);
});

// -----------------------------------------------------------------------
// Idempotency
// -----------------------------------------------------------------------

test('update is idempotent across two consecutive runs', function () {
    config(['laravel-crm.version' => '2.4.0']);

    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])->assertExitCode(Command::SUCCESS);

    $after = Setting::orderBy('name')->pluck('value', 'name')->toArray();

    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->expectsOutputToContain('Laravel CRM is now updated.')
        ->assertExitCode(Command::SUCCESS);

    expect(Setting::orderBy('name')->pluck('value', 'name')->toArray())->toBe($after);
});

test('a second run skips the backfills the first one marked applied', function () {
    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])->assertExitCode(Command::SUCCESS);

    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->doesntExpectOutputToContain('Updating Laravel CRM quote numbers')
        ->doesntExpectOutputToContain('Updating Laravel CRM pipeline tables')
        ->assertExitCode(Command::SUCCESS);
});

// -----------------------------------------------------------------------
// Lookup data fan-out
// -----------------------------------------------------------------------

test('update seeds the lookup data operators used to run by hand', function () {
    // docs/upgrading.md told operators to run laravelcrm:permissions themselves
    // after upgrading. All of these are idempotent, so the command runs them.
    fakeUpdateDependencies();

    $this->artisan('laravelcrm:update', ['--force' => true])
        ->expectsOutputToContain('Seeding lookup data')
        ->assertExitCode(Command::SUCCESS);
});

test('the team-only lookup commands are gated on the teams config', function () {
    $source = file_get_contents(
        (new ReflectionClass(LaravelCrmUpdate::class))->getFileName()
    );

    $gate = strpos($source, "config('laravel-crm.teams')");
    $permissions = strpos($source, "'laravelcrm:permissions'");

    expect($gate)->not->toBeFalse()
        ->and($permissions)->not->toBeFalse()
        ->and($permissions)->toBeGreaterThan($gate)
        ->and($source)->toContain("'laravelcrm:lead-sources'")
        ->and($source)->toContain("'laravelcrm:labels'")
        ->and($source)->toContain("'laravelcrm:addresstypes'")
        ->and($source)->toContain("'laravelcrm:contacttypes'")
        ->and($source)->toContain("'laravelcrm:organizationtypes'");
});

// -----------------------------------------------------------------------
// Removed: the release-specific quantity column check
// -----------------------------------------------------------------------

test('the quantity column check is gone', function () {
    // It existed only to compensate for migrate failures being swallowed. Now
    // that a failed migration exits non-zero, the general mechanism covers it —
    // and the migration itself is pinned by QuantityMigrationTest.
    $source = file_get_contents(
        (new ReflectionClass(LaravelCrmUpdate::class))->getFileName()
    );

    expect($source)->not->toContain('checkQuantityColumns');
});
