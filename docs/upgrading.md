# Upgrade guide

Notes for operators upgrading an existing `venturedrake/laravel-crm` install.

Two things to read: **How to update** below, which is the same every release, and the
**version-specific notes** at the bottom for the release you are moving to. Read the latter
**before** you deploy — some releases change who is allowed to do what, and the failure mode is a
`403` for a user who could previously click the button.

---

## How to update (local)

```bash
composer update venturedrake/laravel-crm
php artisan laravelcrm:update
```

That is the whole procedure. The first command republishes assets and clears caches via the
composer hook; the second applies database changes.

> `composer update && php artisan migrate` is **not** enough. Migrations published before 2.4.0
> ship as `.stub` files that have to be published into your `database/migrations` before the
> migrator can see them, and a stale `manifest.json` will point at asset filenames that no longer
> exist on disk. `laravelcrm:update` does both.

---

## How to update (production deploy)

```bash
composer install --no-dev --optimize-autoloader   # post-autoload-dump fires laravelcrm:upgrade
php artisan laravelcrm:update --force             # migrations + backfills, no prompts
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`--force` skips the production confirmation prompt. `laravelcrm:update` exits non-zero if
migrations or seeding fail, so `&&` chains and CI steps stop where they should.

### Zero-downtime deploys (Envoyer, Deployer, Vapor)

The two commands belong in different hooks:

| Command | Hook | Why |
| --- | --- | --- |
| `laravelcrm:upgrade` | **Build / install** (it fires automatically from `composer install`) | It writes into *that release's* `public/`, so it has to run per release directory, before the symlink flips |
| `laravelcrm:update` | **Activate / after-deploy**, once | It touches the shared database. Running it per server would run the same migrations concurrently |

If your platform builds each release in a fresh directory, nothing extra is needed for the first
one — the composer hook handles it. Just make sure the second is not in a per-server hook.

---

## What each command does

| | `laravelcrm:upgrade` | `laravelcrm:update` |
| --- | --- | --- |
| Republishes built assets (JS/CSS/images) | ✅ | ✅ (calls `upgrade` first) |
| Prunes stale content-hashed build files | ✅ | ✅ |
| Clears cached config, routes, views | ✅ | ✅ |
| Publishes Flasher assets | ✅ | ✅ |
| Publishes migration stubs | ❌ | ✅ |
| Runs `migrate` | ❌ | ✅ |
| Runs seeders and data backfills | ❌ | ✅ |
| Stamps the `db_version` marker | ❌ | ✅ |
| Prompts | never | only on an interactive production console, unless `--force` |
| Exits non-zero on failure | only if asset publishing itself errors | on any failure |

**`laravelcrm:upgrade` never opens a database connection.** That is deliberate: it runs from a
composer hook, which can fire during a build when the database is unreachable, mid-migration, or
belongs to a different release. All database work is in `laravelcrm:update`, which you run
explicitly.

`laravelcrm:update` also runs the lookup-data seeders that this guide used to tell you to run by
hand — `laravelcrm:lead-sources`, and on teams installs `laravelcrm:permissions`,
`laravelcrm:labels`, `laravelcrm:addresstypes`, `laravelcrm:contacttypes` and
`laravelcrm:organizationtypes`. All are idempotent.

---

## One-time step for installs from before 2.4.0

New installs get this from `laravelcrm:install`. Existing hosts add it once, to the host
application's `composer.json`:

```json
"scripts": {
    "post-autoload-dump": [
        "@php artisan package:discover --ansi",
        "@php artisan laravelcrm:upgrade --ansi"
    ]
}
```

Then run `composer dump-autoload` to confirm it fires. From that point on, every `composer install`
and `composer update` republishes CRM assets and clears caches on its own.

The line must come **after** `package:discover` — that is what makes the package's artisan
commands resolvable.

---

## Caveats

**Published views are frozen.** If you ran `vendor:publish --tag=views`, your host's copies in
`resources/views/vendor/laravel-crm` shadow the package's and will *not* pick up template changes
from a release. Re-publish with `--force` and re-apply your edits, or diff the package's
`resources/views` against your copies before upgrading. The same applies to published lang files.

**New config keys arrive automatically, but caches hide them.** Keys added to
`config/package.php` and `config/laravel-crm.php` reach your app through `mergeConfigFrom`, so you
do not need to re-publish the config. A stale `php artisan config:cache` from the previous release
will hide them — the composer hook runs `config:clear` for exactly this reason. Keys you have
overridden in your published `config/laravel-crm.php` stay as you set them.

**The database can be behind the code without anything looking wrong.** The CRM stamps a
`db_version` setting when `laravelcrm:update` completes and reports a banner when the installed
code is ahead of it, or when one of *this package's* migrations has not run. Migrations belonging
to your own application or to other packages are not counted — an unrun migration of yours will
never raise a CRM banner. If you see *"Your Laravel CRM version requires some database updates"*,
run `php artisan laravelcrm:update`.

**Rolling back the package does not roll back the database.** Migrations are not reversed by
downgrading the composer constraint. Restore from a backup if you need to go back.

**Remove the composer hook before you remove the package.** `post-autoload-dump` fires on
`composer remove venturedrake/laravel-crm` too, at which point `laravelcrm:upgrade` no longer
exists and composer reports the script returned a non-zero exit code. Delete the
`@php artisan laravelcrm:upgrade --ansi` line from your `composer.json` first.

---

## Version-specific notes

## 2.4.0

### Migrations no longer need publishing

Migrations added from this release ship as real `.php` files inside the package and are loaded
with `loadMigrationsFrom`, so a plain `php artisan migrate` runs them. The existing `.stub` set is
frozen and still published into your `database/migrations` — **existing hosts are unaffected and
keep the filenames they already have.**

One related change: newly published stubs are now stamped from a fixed `2024_01_01` epoch rather
than the moment of publishing, so a fresh install orders them correctly against the package-loaded
migrations. This only affects stubs that have never been published on a given host; anything
already in your `database/migrations` keeps its name.

### `laravelcrm:update` now fails loudly

It used to catch migration and seeder exceptions, print a warning, and still report
`Laravel CRM is now updated.` with exit code 0 — so a broken upgrade and a clean one looked
identical in a deploy log. It now prints an error and exits non-zero. **If your deploy script was
relying on it always succeeding, it will now stop where it previously carried on.** That is the
point, but check your pipeline for it.

### Line item quantities become decimal — six `ALTER TABLE`s

Line item `quantity` widens from `integer` to `decimal(15,3)` so a product can be sold by
weight or volume (3.5 Kg, 0.25 L). `php artisan laravelcrm:update` publishes and runs
`change_quantity_to_decimal_on_laravel_crm_tables`, which alters six tables:

| Table |
| --- |
| `crm_quote_products` |
| `crm_order_products` |
| `crm_deal_products` |
| `crm_invoice_lines` |
| `crm_purchase_order_lines` |
| `crm_delivery_products` |

**No data is lost.** `decimal(15,3)` strictly contains the old `INT` range
(2,147,483,647), so every existing row widens exactly. NULL quantities stay NULL. Nothing
needs backfilling.

**Plan for a brief write lock.** On MySQL each `ALTER` rewrites the table. On a small CRM
that is a fraction of a second; on a large install with millions of invoice lines, budget
for it or run the migration in a maintenance window. Postgres is likewise a table rewrite.

**Rolling back truncates.** `down()` puts the column back to `integer`, which discards the
decimal part of any quantity entered since. There is no lossless inverse — if you need to
roll back after users have entered fractional quantities, export those rows first.

**Verify afterwards.** `laravelcrm:update` now exits non-zero when a migration fails, so its
output can be trusted — but if you ran an older build of it, which swallowed failures and printed
success anyway, confirm the change actually landed:

```sql
SHOW COLUMNS FROM crm_quote_products LIKE 'quantity';   -- decimal(15,3), Null: YES
```

Two behaviour changes ride along with it:

- **The Order → Invoice and Order → Delivery quantity dropdown is now a number input.** A
  dropdown cannot express 3.5. The cap on the outstanding quantity is unchanged but has
  moved server-side — previously it existed only in the browser, so an over-invoice was
  reachable by anyone posting the form directly. On submit the remainder is recomputed
  from the order line and the invoices or deliveries already raised against it, so a
  request is checked against the database rather than against anything it sent.
- **The API `quantity` field is now a JSON number rather than an integer.** See
  [docs/api.md](api.md#quantity-accepts-decimals-up-to-3-places).

---

### Authorization is now enforced on every mutating action

This release closes a long-standing gap: the UI has always advertised permissions via Blade
`@can` directives, but the Livewire components behind those buttons did not re-check them on the
server. Anyone who could reach a CRM page could invoke its actions directly over the Livewire
endpoint, regardless of role.

Every mutating Livewire action, every route group that previously had none, and the Blade
controls that trigger them now enforce the **same** Spatie permission the UI already advertised.
**No new permission name is introduced anywhere in this change** — the guards use permissions
that already ship in `LaravelCrmTablesSeeder`.

Concretely, the following now return `403` instead of silently succeeding:

- Livewire actions that create, update, delete, send, pay, complete, accept/reject, or reorder a
  record (leads, deals, quotes, orders, invoices, deliveries, purchase orders, products, tasks,
  people, organizations, notes/calls/meetings/lunches/files, settings lookups, chat, imports,
  templates, campaigns).
- Route groups that shipped ungated: `activities/*` and the deal/quote/order `products`
  sub-resources.
- Deleting a user who is not in your current team, and assigning the `Owner` role if you are not
  an Owner yourself.

Kanban cards are no longer draggable for users without the matching `edit` permission (previously
they were draggable and the drop 403'd), and mutating buttons/menu items are hidden rather than
shown-then-denied.

---

### 1. Re-run the permission seeder first — before deploying the new code

**This is the most likely cause of unexpected 403s after upgrade, and it is entirely
preventable.**

An install that has been upgraded over time without re-seeding may be missing permissions added
in later releases. Those permissions did not matter before, because the actions they gate were
not enforced. They matter now. The six families most commonly missing on long-lived installs:

| Permission family | Added for |
| --- | --- |
| `crm monitors` | Uptime / SSL monitoring |
| `crm features` | Feature voting & feedback portal |
| `crm email-campaigns` | Email marketing |
| `crm sms-campaigns` | SMS marketing |
| `crm chat` | Live chat |
| `crm activities` | Activity timeline |

If the permission row does not exist, no role holds it, and every action it gates will `403` —
**including for Owner and Admin**, because those roles are granted `Permission::all()` *as it
existed at the moment they were seeded*.

**Run this before you deploy:**

```bash
# 1. Creates any missing permission rows and re-grants them to the stock roles.
php artisan laravelcrm:update

# …or, to re-run only the seeder without also migrating:
php artisan db:seed --class="VentureDrake\LaravelCrm\Database\Seeders\LaravelCrmTablesSeeder" --force

# 2. Multi-tenant (laravel-crm.teams = true) installs ONLY — run after step 1.
#    laravelcrm:update now runs this for you; it is listed here so you can run it
#    on its own, and so the ordering is explicit.
php artisan laravelcrm:permissions
```

**Step 1 is the step that fixes missing permissions, and it is safe to re-run.** Every permission
is created with `Permission::firstOrCreate(...)` and every role with
`Role::firstOrCreate(...)->givePermissionTo(...)`, so existing rows are matched rather than
duplicated and existing grants are additive. Re-seeding revokes nothing and does not touch custom
roles.

**`php artisan laravelcrm:permissions` is not a substitute for step 1.** Despite the name it
creates no permissions — it copies the global CRM roles and their *existing* grants down to each
team, so it only does anything when `laravel-crm.teams = true`. Run on a single-tenant install it
prints `Teams config for multi-tenant support is not enabled.` and exits without changing
anything. If that message is all you saw, the missing permissions are still missing: go back and
run step 1.

Verify before you deploy:

```bash
php artisan tinker
>>> Spatie\Permission\Models\Permission::where('name', 'like', '%crm monitors%')->count();  // expect 4
>>> Spatie\Permission\Models\Role::where('name', 'Owner')->first()->permissions->count();   // expect all
```

---

### 2. Custom roles with view-only permissions lose actions they could previously perform

This is **correct behaviour**, not a bug — but it will generate support traffic, so audit it
before you upgrade rather than after.

If your team built custom roles under **Settings → Roles** (for example a "Read only" or
"Support" role granted `view crm leads` but not `edit crm leads`), those users could previously
still edit, delete, and reorder records by using the on-screen controls. The UI hid some of the
buttons; the server did not check. After this upgrade the server checks, so those actions return
`403` and the buttons no longer render.

Audit your custom roles before deploying:

```bash
php artisan tinker
>>> Spatie\Permission\Models\Role::where('crm_role', 1)
...     ->whereNotIn('name', ['Owner', 'Admin', 'Manager', 'Employee'])
...     ->get()
...     ->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')]);
```

For each custom role, ask whether the people holding it are *expected* to perform the actions
they have been performing. If yes, grant the matching `create` / `edit` / `delete` permission.
If no, nothing to do — the upgrade is the fix. Either way, tell those users first.

---

### 3. Installs with a trimmed `config('laravel-crm.modules')` will 403 on the disabled module

Every policy gates its methods on an `isEnabled()` helper:

```php
protected function isEnabled()
{
    if (is_array(config('laravel-crm.modules')) && in_array('deals', config('laravel-crm.modules'))) {
        return true;
    } elseif (! config('laravel-crm.modules')) {
        return true;
    }
}
```

Note the missing `else`: when `modules` **is** an array but does **not** contain the module,
`isEnabled()` returns `null`. Every policy method reads `if ($this->isEnabled() && $user->hasPermissionTo(...))`,
so a disabled module denies the action no matter which permissions the user holds — Owner
included.

Before this release that only affected surfaces which already called `authorize()`. Now it
affects every mutating action in the module. If you have trimmed `modules` in
`config/laravel-crm.php` but left the module's UI reachable, users will hit `403`.

Check what is enabled:

```bash
php artisan tinker
>>> config('laravel-crm.modules');
```

If a module is listed in your config it behaves normally. If it is absent and you still expect
people to use it, add it back to the array. If it is absent deliberately, confirm the module's
navigation is also hidden — the module toggles and the `@has{module}enabled` Blade directives
already handle this for the shipped views.

---

### What does not change

Verified against `database/seeders/LaravelCrmTablesSeeder.php`:

- **Owner and Admin lose nothing.** Both are granted `Permission::all()`
  ([`LaravelCrmTablesSeeder.php:569-570`](../database/seeders/LaravelCrmTablesSeeder.php#L569-L570)
  and [`:578-579`](../database/seeders/LaravelCrmTablesSeeder.php#L578-L579)), so they satisfy
  every new guard — provided the permission rows exist, which is exactly what step 1 above
  guarantees.
- **Manager and Employee lose nothing on the core entities.** Both hold
  `create` / `view` / `edit` / `delete` on leads, deals, quotes, orders, invoices, deliveries,
  purchase orders, people, organizations, contacts, activities, tasks, notes, calls, meetings,
  lunches, files, pipelines, and features.
- **No new permission name is introduced anywhere in this change.** Every guard reuses a
  permission that already ships in the seeder.

Three pre-existing gaps in the stock roles, unchanged by this release but worth knowing about
because they are now enforced rather than merely advertised:

- Neither Manager nor Employee holds any `crm monitors` permission — monitoring is Owner/Admin
  only by default.
- Employee holds `view crm chat` and `reply crm chat` only, and holds no `crm email-campaigns` or
  `crm sms-campaigns` permissions.
- Manager holds no `crm customers` permission (Employee does).

If your Managers or Employees are expected to run campaigns, manage monitors, or edit customers,
grant those permissions explicitly under **Settings → Roles** before you upgrade.

---

### Release type

**This ships as a minor release, not a patch.** It changes observable behaviour for existing
users: actions that previously succeeded can now return `403`. Treat it as a minor version bump
with a prominent security note in your own release communications, and give your admins the
heads-up in step 2 before you deploy.
