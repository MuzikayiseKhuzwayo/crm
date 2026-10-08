# Security, Access Control & Field Encryption

Enterprise customer relationship management demands rigorous data isolation, defense-in-depth authorization, and cryptographic protection of sensitive personal data.

---

## 1. Multi-Layer Access Control

Access control in Laravel CRM operates across three complementary layers:

### Layer A: Route Middleware
- `Authenticate`: Ensures the user is logged into the host application.
- `HasCrmAccess`: Verifies that the user has the `crm_access` flag enabled.
- `TeamsPermission`: When `LARAVEL_CRM_TEAMS=true`, verifies valid active team context.
- `LogUsage`: Records API and feature usage in the `crm_usage_requests` table.

### Layer B: Role-Based Permissions (Spatie)
Roles and permissions are seeded via `php artisan laravelcrm:permissions`. Four default roles are provided:
- **Owner**: Complete system control, billing, user management, and updates.
- **Admin**: Organizational pipeline management, user provisioning, and settings.
- **Manager**: Team-level deal approvals, reporting, and activity auditing.
- **Employee**: Daily pipeline execution, task completion, and contact management.

### Layer C: Eloquent Model Policies
41 dedicated policy classes in `src/Policies/` govern fine-grained record permissions (`view`, `create`, `update`, `delete`, `restore`, `forceDelete`).

---

## 2. At-Rest Database Field Encryption

To comply with GDPR, HIPAA, and CCPA standards, Laravel CRM provides zero-effort transparent field encryption via `LaravelEncryptable`.

### How It Works
When enabled via `LARAVEL_CRM_ENCRYPT_DB_FIELDS=true`, sensitive fields (person first name, last name, phone numbers, email addresses) are encrypted using AES-256-CBC before hitting the database storage engine.

```php
protected $encryptable = [
    'first_name',
    'last_name',
    'email',
    'phone',
];
```

### Encryption & Decryption Ops Commands
To migrate existing plaintext data to encrypted storage, or decrypt data for external database migrations:
```bash
php artisan laravelcrm:encrypt   # Encrypts all existing records
php artisan laravelcrm:decrypt   # Restores plaintext storage
```

### Free-Text Contact Searching
The `SearchesEncryptableContacts` trait performs blind-index and decrypt-filter searches in memory, allowing operators to search for customer names and emails even when the underlying database columns are encrypted.

---

## 3. Cross-Domain Ingress Isolation

Public-facing assets—such as customer quote approval portals (`/p/quotes/{id}`), tracking pixels (`/p/email/o/*`), and the visitor chat widget embed (`/p/chat/{publicKey}`)—are registered outside the default `web` middleware group. This eliminates CSRF mismatches when widgets are embedded across external marketing domains and prevents third-party tracking scripts from polluting authenticated operator sessions.
