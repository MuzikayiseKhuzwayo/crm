# Upgrade guide

Notes for operators upgrading an existing `venturedrake/laravel-crm` install. Read the section
for the release you are moving to **before** you deploy — some releases change who is allowed to
do what, and the failure mode is a `403` for a user who could previously click the button.

---

## Upgrading

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

**Verify afterwards.** `laravelcrm:update` swallows migration failures and still prints
success, so confirm the change landed rather than trusting the output:

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
