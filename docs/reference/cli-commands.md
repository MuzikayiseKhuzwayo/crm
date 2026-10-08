# Artisan CLI Command Reference

Laravel CRM ships with a comprehensive suite of artisan console commands prefixed by `laravelcrm:`.

---

## 1. Setup, Seeding & Upgrades

| Command | Description | Schedule |
|---|---|---|
| `laravelcrm:install` | Complete initial installation, publishes assets, migrates DB, seeds defaults | Manual |
| `laravelcrm:update` | Checks for updates, runs pending migrations, clears caches | Post-update |
| `laravelcrm:upgrade` | Assets and schema synchronizer for version upgrades | Post-update |
| `laravelcrm:v2` | One-shot migration helper to migrate legacy v1 data to v2 structure | One-time |
| `laravelcrm:permissions` | Seeds Spatie roles (`Owner`, `Admin`, `Manager`, `Employee`) and 41+ policies | Manual |
| `laravelcrm:sample-data` | Generates realistic sample leads, deals, quotes, and customers for dev | Manual |
| `laravelcrm:add-user` | Adds or upgrades an existing user with CRM access and assigns a role | Manual |
| `laravelcrm:address-types` | Seeds default address categories (Office, Billing, Shipping) | Manual |
| `laravelcrm:contact-types` | Seeds contact classification types | Manual |
| `laravelcrm:organization-types`| Seeds organization corporate classification types | Manual |
| `laravelcrm:lead-sources` | Seeds initial lead sources (Website, Referral, LinkedIn, etc.) | Manual |
| `laravelcrm:labels` | Seeds default color-coded visual labels | Manual |
| `laravelcrm:fields` | Seeds system default custom fields and custom field groups | Manual |

---

## 2. Ingestion & Sales Automation

| Command | Signature & Options | Purpose |
|---|---|---|
| `laravelcrm:import-linkedin-leads` | `import-linkedin-leads {file.json} [--user=1] [--dry-run]` | Imports and deduplicates leads from LinkedIn JSON search export |
| `laravelcrm:sync-lead-stages` | `sync-lead-stages [--lead=ID] [--dry-run]` | Auto-advances lead stage and status based on completed tasks |
| `laravelcrm:generate-playbook-tasks` | `generate-playbook-tasks [--lead=ID] [--stage=ID] [--limit=0] [--force]` | Generates structured outbound sales tasks based on angle detection |
| `laravelcrm:sync-leads-from-sqlite` | `sync-leads-from-sqlite [--dry-run]` | Synchronizes leads, contacts, orgs, and tasks from local SQLite |
| `laravelcrm:setup-lead-pipeline` | `setup-lead-pipeline [--fresh]` | Sets up or resets default sales pipeline and probability stages |
| `laravelcrm:seed-linkedin-tasks` | `seed-linkedin-tasks [--dry-run]` | Seeds automated task sequences for imported leads |
| `laravelcrm:prune-template-leads` | `prune-template-leads [--dry-run]` | Safely removes test/sample leads created during testing |

---

## 3. Scheduled Background Workers

| Command | Purpose | Default Schedule |
|---|---|---|
| `laravelcrm:reminders` | Evaluates upcoming and overdue tasks, calls, and meetings; sends alerts | `* * * * *` (Every minute) |
| `laravelcrm:email-campaigns-dispatch` | Dispatches pending email campaign batches via queue | `* * * * *` (Every minute) |
| `laravelcrm:sms-campaigns-dispatch` | Dispatches scheduled SMS campaign messages via ClickSend API | `* * * * *` (Every minute) |
| `laravelcrm:archive` | Archives stale leads, completed activities, and expired sessions | `0 0 * * *` (Daily) |
| `laravelcrm:monitor-check` | Pings configured HTTP monitors, verifies SSL expiry, tracks latency | `*/5 * * * *` (Every 5 mins) |

---

## 4. Security & Integrations

| Command | Signature & Options | Purpose |
|---|---|---|
| `laravelcrm:encrypt` | `encrypt` | Encrypts plaintext database fields using `LaravelEncryptable` |
| `laravelcrm:decrypt` | `decrypt` | Decrypts encrypted database fields back to plaintext |
| `laravelcrm:xero` | `xero contacts` / `xero products` | Synchronizes contacts and inventory items with Xero |
| `laravel-crm:api-token` | `api-token {email} [--name="Token Name"]` | Generates a Sanctum REST API bearer token for an operator |
