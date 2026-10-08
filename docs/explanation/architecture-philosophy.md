# Architecture & Design Philosophy

Laravel CRM is designed as a drop-in Laravel package rather than a standalone siloed monolith. This architectural choice gives developers complete control to embed enterprise-grade CRM capabilities directly within their SaaS or line-of-business applications without running separate services or data synchronizers.

---

## 1. The 3-Layer Separation of Concerns

```
Routes (src/Http/*)
  ├── Web Routes: src/Http/routes.php (auth.laravel-crm, crm middleware stack)
  ├── API Routes: src/Http/api-routes.php (Sanctum tokens, rate limiting)
  └── Portal Routes: src/Http/portal-routes.php & chat-embed-routes.php
        ↓
Layer 1: Presentation (Livewire 3/4 + MaryUI + DaisyUI)
  ├── Stateful UI components (src/Livewire/*)
  ├── Reusable sub-components (ModelPhones, ModelAddresses, KanbanBoard)
  └── Public Portal Views (Client quote approval, PDF streaming)
        ↓
Layer 2: Gateway & Business Orchestration
  ├── Thin Controllers (return view / redirect; zero business logic)
  ├── Domain Services (src/Services/* - LeadService, DealService, etc.)
  └── Authorization Policies (41+ policies registering with Laravel Gate)
        ↓
Layer 3: Deterministic Data Engine
  ├── Base Model (src/Models/Model.php with saveQuietly wrapper)
  ├── 87 Eloquent Models (BelongsToTeamsScope, encryptable fields)
  └── 58 Model Observers (UUID external_id generation, human-readable numbers)
```

---

## 2. Key Design Decisions

### External UUIDs vs. Auto-Increment Primary Keys
In public URLs, REST API payloads, and webhook events, records are identified exclusively by `external_id` (a UUID v4). Auto-incrementing integers (`id`) are preserved internally for blazing-fast database index joins and foreign key constraints, but are strictly hidden from external exposure.

### Human-Readable Serial Codes
Customers and sales teams think in serial identifiers rather than UUIDs. Model observers automatically generate human-readable keys on `creating`:
- Leads: `L1001`, `L1002`
- Deals: `D1001`
- Quotes: `Q1001`
- Invoices: `INV1001`
- Deliveries: `DEL1001`
- Purchase Orders: `PO1001`

Prefixes are dynamically configurable through the Settings UI.

### Thin Controllers, Heavy Services
Controllers in Laravel CRM never contain business logic, database transactions, or external API calls. They solely authorize the incoming request and pass data to a dedicated domain `Service` (`LeadService`, `OrderService`, `InvoiceService`). This ensures that the exact same business logic executes whether an action is triggered from the Web UI, the REST API, or an Artisan CLI command.

### Multi-Tenancy via Global Scopes
When `LARAVEL_CRM_TEAMS=true`, the `BelongsToTeams` trait attaches `BelongsToTeamsScope` to all CRM queries. Every query is automatically scoped to `auth()->user()->currentTeam`, preventing cross-tenant data leaks at the ORM layer.
