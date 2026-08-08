# Package-loaded migrations

Every migration added to Laravel CRM from 2.4.0 onwards belongs **here**, as a real
`YYYY_MM_DD_HHMMSS_description.php` file.

`LaravelCrmServiceProvider::boot()` registers this directory with `loadMigrationsFrom()`, so the
files in it run on every host from a plain `php artisan migrate`. There is nothing to publish and
nothing to register.

## Why not `database/migrations`?

That directory holds `.stub` files that have to be published into the host's own
`database/migrations` before the migrator can see them — Laravel globs `*_*.php`, so a `.stub` is
invisible. Each one also needs a hand-picked order number in a 137-entry array in the service
provider, and forgetting either half means the migration silently never reaches anyone.

**That stub set is frozen.** It exists only so hosts that already published from it keep receiving
in-place fixes. Do not add to it.

## Ordering

Published stubs are stamped from a fixed `2024-01-01` epoch (see
`LaravelCrmServiceProvider::getMigrationFileName`), so a file dated today sorts after the entire
stub set on a fresh install as well as on an existing one. Name new files with the date you write
them.

## Conventions

Same as the rest of the package:

- Prefix table names with `config('laravel-crm.db_table_prefix')` (default `crm_`).
- Guard structural changes with `Schema::hasTable()` / `Schema::hasColumn()` — hosts arrive here
  from a wide range of past versions, and `laravelcrm:update` is expected to be re-runnable.
- Write a real `down()` where one exists, and say so in `docs/upgrading.md` when it is lossy.
