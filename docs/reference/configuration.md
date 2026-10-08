# Configuration Reference

Laravel CRM configuration is defined in `config/laravel-crm.php`. Every setting can be tuned using environment variables in `.env`.

---

## 1. Core Configuration Keys

| Config Key | Environment Variable | Default | Description |
|---|---|---|---|
| `db_table_prefix` | `LARAVEL_CRM_DB_TABLE_PREFIX` | `'crm_'` | Prefix applied to all package database tables |
| `route_prefix` | `LARAVEL_CRM_ROUTE_PREFIX` | `'crm'` | URL prefix for CRM routes (`/crm`) |
| `route_subdomain` | `LARAVEL_CRM_ROUTE_SUBDOMAIN` | `null` | Optional subdomain (e.g. `'crm'` for `crm.yourdomain.com`) |
| `teams` | `LARAVEL_CRM_TEAMS` | `false` | Enable multi-tenant team isolation mode |
| `encrypt_db_fields` | `LARAVEL_CRM_ENCRYPT_DB_FIELDS` | `false` | Enable symmetric AES-256 field encryption for PII |
| `user_interface` | `LARAVEL_CRM_UI` | `true` | Load Blade & Livewire web UI (set false for headless API only) |
| `docs_url` | `LARAVEL_CRM_DOCS_URL` | `'https://github.com/MuzikayiseKhuzwayo/crm'` | Target URL for documentation links |
| `upgrade_guide_url`| `LARAVEL_CRM_UPGRADE_GUIDE_URL` | `'https://laravelcrm.com/docs/2.x/upgrading'` | Target URL for upgrade guide links |

---

## 2. Feature Modules Toggle

The `modules` array selectively enables or disables specific CRM functional domains. When a module is omitted, its navigation items, routes, and background jobs are suppressed:

```php
'modules' => [
    'leads',
    'deals',
    'quotes',
    'orders',
    'invoices',
    'deliveries',
    'purchase-orders',
    'teams',
    'chat',
    'email-marketing',
    'sms-marketing',
    'monitoring',
],
```

---

## 3. Integration Settings

### ClickSend SMS
Credentials are automatically stored in the CRM settings database table (`crm_settings`) or seeded via `.env`:
- `clicksend_username`: ClickSend API username
- `clicksend_api_key`: ClickSend API key
- `clicksend_default_from`: Sender phone number or registered alphanumeric header

### Xero Accounting
Powered by `dcblogdev/laravel-xero`:
- `XERO_CLIENT_ID`: OAuth2 Client ID
- `XERO_CLIENT_SECRET`: OAuth2 Client Secret
- `XERO_REDIRECT_URI`: OAuth2 callback endpoint
