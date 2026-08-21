# Setup, Scaling, Growth & Deployment Guide — Laravel CRM

---

## 📌 Executive Summary

`venturedrake/laravel-crm` is a feature-rich, multi-tenant CRM package supporting Laravel 11–13 (PHP 8.2+). It ships with full sales pipeline management, interactive quote & invoice builders, contact management, activity tracking, custom field groups (EAV), email/SMS marketing, and embeddable web chat.

This document details:
1. Local & Production Setup Requirements
2. Database Schema Architecture & Indexing Strategy
3. Data Scaling, Storage Metrics & High-Volume Growth Strategy
4. Deployment Architecture Comparison (Cloud Run vs Compute Engine vs Firebase vs Forge)

---

## 🛠️ 1. Environment & Setup Requirements

### System Requirements
- **PHP**: `^8.2`, `^8.3`, `8.4+`
  - Required Extensions: `ext-pdo_sqlite` (or `pdo_mysql`/`pdo_pgsql`), `ext-openssl`, `ext-mbstring`, `ext-curl`, `ext-gd`, `ext-intl`, `ext-zip`
- **Node.js**: `^18.0` or `^20.0`
- **Composer**: `^2.2`

### Key Artisan Commands
```bash
# Package Setup & Migrations
php artisan laravelcrm:install       # Publish config & initial migrations
php artisan migrate:fresh            # Execute all database migrations
php artisan laravelcrm:permissions   # Seed default Spatie roles & permissions
php artisan laravelcrm:lead-sources  # Seed lead sources
php artisan laravelcrm:labels        # Seed custom labels
php artisan laravelcrm:addresstypes  # Seed address types
php artisan laravelcrm:organizationtypes # Seed organization types
php artisan laravelcrm:contacttypes  # Seed contact types

# Seed Pipelines & Sample Data
php artisan db:seed --class="VentureDrake\LaravelCrm\Database\Seeders\LaravelCrmPipelineTablesSeeder"
php artisan laravelcrm:sample-data   # Generate sample CRM records (use --full for 500k+ records)

# Maintenance & Version Updates
php artisan laravelcrm:update        # Sync database migration flags & reseed lookups
php artisan laravelcrm:archive       # Run daily record archiving
php artisan laravelcrm:reminders     # Trigger activity notifications (every minute)
php artisan laravelcrm:email-campaigns-dispatch # Dispatch due email campaigns
php artisan laravelcrm:sms-campaigns-dispatch   # Dispatch due SMS campaigns
```

---

## 🗄️ 2. Database Architecture & Schema Design

All package database tables use the prefix configured in `config('laravel-crm.db_table_prefix')` (default `crm_`).

### Storage Domains & Table Matrix

| Domain | Database Tables | Structural Highlights & Indexing |
|---|---|---|
| **Pipeline & Sales** | `crm_pipelines`<br>`crm_pipeline_stages`<br>`crm_pipeline_stage_probabilities`<br>`crm_leads`<br>`crm_deals` | • Stages ordered by integer `order` column.<br>• Human IDs (`LD-1001`, `DL-1001`) generated on `creating` observer.<br>• Indexed `external_id` (UUIDv4) for safe public API / route binding.<br>• Amounts stored as **integer cents** (`amount * 100`) to avoid float precision issues. |
| **Document Builder** | `crm_quotes` & `_products`<br>`crm_orders` & `_products`<br>`crm_invoices` & `_lines`<br>`crm_deliveries` & `_products`<br>`crm_purchase_orders` & `_lines` | • Direct relationships to `Product`, `ProductVariation`, and `TaxRate`.<br>• Public portal access via `/p/quotes/{external_id}` and `/p/invoices/{external_id}`.<br>• Formatted dynamically via `cknow/laravel-money`. |
| **Contacts & Orgs** | `crm_people`<br>`crm_organizations`<br>`crm_contacts`<br>`crm_addresses`<br>`crm_emails`<br>`crm_phones` | • Polymorphic linkages (`emailable_type`/`emailable_id`, `phoneable_type`/`phoneable_id`).<br>• Sensitive PII fields (names, emails, phones) encrypted via `LaravelEncryptableTrait`. |
| **Activities** | `crm_activities`<br>`crm_tasks`<br>`crm_notes`<br>`crm_calls`<br>`crm_meetings`<br>`crm_lunches`<br>`crm_files` | • Polymorphic timeline aggregations (`timelineable_type`, `timelineable_id`).<br>• High-write frequency domain. |
| **Marketing & Chat** | `crm_email_campaigns` & `_recipients`<br>`crm_sms_campaigns` & `_recipients`<br>`crm_chat_widgets`, `_conversations`, `_messages`, `_visitors` | • Background dispatches queued to avoid HTTP blocking.<br>• Cross-origin web chat embed served at `/p/chat/{publicKey}`. |
| **EAV Custom Fields** | `crm_field_groups`<br>`crm_fields`<br>`crm_field_options`<br>`crm_field_models`<br>`crm_field_values` | • Allows dynamic custom input fields per model without schema migrations. |

---

## 📈 3. Data Growth & Scaling Considerations

### Table Growth Projections

| Table Name | Growth Pace | Recommended Strategy |
|---|---|---|
| `crm_activities`, `crm_notes`, `crm_tasks`, `crm_calls`, `crm_meetings`, `crm_lunches` | **Very High** (~100k+ records / year / 10 reps) | Ensure composite indexes on `(timelineable_type, timelineable_id)` and `created_at`. Prune old completed activities. |
| `crm_usage_requests` | **High** (1 record per HTTP request) | Run periodic pruning via `UsageRequest::where('created_at', '<', now()->subDays(90))->delete();`. |
| `crm_email_campaign_recipients`, `crm_sms_campaign_recipients` | **High** (Scales with campaign list sizes) | Store logs in partition or archive periodically after campaign completion. |

### PII Field Encryption & Search Mechanics
- Sensitive fields in `crm_people`, `crm_organizations`, `crm_emails`, and `crm_phones` are encrypted using AES-256-CBC via `LaravelEncryptableTrait`.
- Because encrypted ciphertext cannot be indexed with standard SQL `LIKE '%term%'`, full-text search across contacts is handled via `SearchesEncryptableContacts` trait, which evaluates chunked collections.
- **Scaling Recommendation**: At >100,000 contact records, integrate **Meilisearch** or **Algolia** via Laravel Scout to maintain sub-10ms search speeds over encrypted contact fields.

---

## 🌐 4. Cloud & Hosting Architecture Options

### ❌ Option 0: Rewriting in Node/Go for Firebase (NOT RECOMMENDED)
- **Why Avoid**: `laravel-crm` contains **55,000+ lines of battle-tested code**, 12+ full sales modules, 1,450+ unit/feature tests, EAV systems, PDF generators, and Livewire UI.
- **Cost**: A full rewrite would require 6–12 months of senior engineering work, costing tens of thousands of dollars, while introducing huge security and feature regressions.

---

### Option A (Recommended — Modern Cloud Native): Google Cloud Run + Cloud SQL + Firebase Hosting Edge
- **Architecture**:
  - **Stateless App**: Package containerized with Docker (PHP 8.4 + NGINX) deployed to **Google Cloud Run**.
  - **Database**: **Google Cloud SQL** (MySQL 8.0 or PostgreSQL 15+).
  - **CDN Edge**: **Firebase Hosting** proxies dynamic requests (`/crm/**`, `/p/**`, `/livewire/**`) to Cloud Run while serving static Vite assets (`/public/vendor/laravel-crm`) directly from Firebase CDN at 5ms latency.
- **Pros**:
  - Scales automatically from 0 to thousands of concurrent requests.
  - Pay-per-use: Costs near $0/month when idle.
  - Firebase Hosting handles global SSL & static asset caching.
- **Cons**:
  - Requires containerizing with Docker & configuring Cloud SQL connection proxy.

---

### Option B (Simplest Production Deployment): Laravel Forge / Ploi + VPS (Compute Engine / Hetzner / DigitalOcean)
- **Architecture**:
  - Deploy to a standard Virtual Machine (e.g., Google Compute Engine `e2-standard-2` or DigitalOcean Droplet).
  - Provisioned automatically using **Laravel Forge** or **Ploi.io**.
  - Server runs NGINX, PHP 8.4, MySQL 8, Redis, Supervisor (Queue Workers), and Certbot (SSL).
- **Pros**:
  - 10-minute automated setup with 0 DevOps overhead.
  - Standard monolith performance — fast, reliable, zero cold starts.
  - Easy queue management for email/SMS dispatches.
- **Cons**:
  - Fixed monthly cost ($5–$20/mo server cost).
  - Manual scaling if user base grows to tens of thousands of concurrent users.

---

### Option C (Internal / On-Premise / Local Host):
- Keep hosted locally or on an internal company server for private team access.
- Expose externally via **Cloudflare Tunnels** or **Ngrok** if remote access or webhook integrations (ClickSend, Xero) are required.

---

## 🏁 Summary Recommendation

1. **For Production Cloud Hosting**: Deploy via **Google Cloud Run + Cloud SQL** with **Firebase Hosting** as the static CDN, OR use **Laravel Forge + Google Compute Engine** for simple, maintenance-free management.
2. **Do NOT Rewrite**: Retain the complete, tested PHP/Laravel codebase.
3. **Database Selection**: Use **MySQL 8.0** or **PostgreSQL 15+** in production for multi-tenant scalability.
