# Laravel CRM REST API Reference (v2)

Machine-accurate reference documentation for the Laravel CRM REST API (v2). All endpoints are prefixed by `<host>/crm/api/v2/` and return strict `application/json` responses.

---

## 1. Authentication & Security

The API uses **Laravel Sanctum** personal access tokens. All authenticated requests must include the bearer token in the `Authorization` header:

```http
Authorization: Bearer <your-sanctum-token>
Accept: application/json
```

### Rate Limiting
- Token Issue: `6 requests / minute`
- General API: `60 requests / minute` (configured via `throttle:laravel-crm-api`)

---

## 2. Endpoints Summary

| Method | URI | Description | Scope / Param |
|---|---|---|---|
| `POST` | `/auth/token` | Issue personal access token with email & password | Rate-limited |
| `GET` | `/auth/me` | Inspect current authenticated user and permissions | Authenticated |
| `DELETE` | `/auth/token` | Revoke the current access token | Authenticated |
| `GET\|POST` | `/leads` | List or create leads | Filter by `user_owner_id`, sort |
| `GET\|PUT\|DELETE` | `/leads/{id}` | Retrieve, update, or soft-delete a lead | UUID `external_id` |
| `GET\|POST` | `/deals` | List or create deals | Filter by `user_owner_id`, sort |
| `GET\|PUT\|DELETE` | `/deals/{id}` | Retrieve, update, or soft-delete a deal | UUID `external_id` |
| `GET\|POST` | `/quotes` | List or create quotes | Full line items & tax support |
| `GET\|PUT\|DELETE` | `/quotes/{id}` | Retrieve, update, or soft-delete a quote | UUID `external_id` |
| `GET\|POST` | `/orders` | List or create orders | Connected to quotes/deals |
| `GET\|PUT\|DELETE` | `/orders/{id}` | Retrieve, update, or soft-delete an order | UUID `external_id` |
| `GET\|POST` | `/invoices` | List or create invoices | Supports billing amounts |
| `GET\|PUT\|DELETE` | `/invoices/{id}` | Retrieve, update, or soft-delete an invoice | UUID `external_id` |
| `GET\|POST` | `/deliveries` | List or create product deliveries | Linked to orders & items |
| `GET\|PUT\|DELETE` | `/deliveries/{id}` | Retrieve, update, or delete delivery | UUID `external_id` |
| `GET\|POST` | `/purchase-orders` | List or create supplier purchase orders | Linked to organizations |
| `GET\|PUT\|DELETE` | `/purchase-orders/{id}` | Retrieve, update, or delete purchase order | UUID `external_id` |
| `GET\|POST` | `/tasks` | List or create tasks | Filter by status, owner |
| `GET\|PUT\|DELETE` | `/tasks/{id}` | Retrieve, update, or delete task | UUID `external_id` |
| `GET\|POST` | `/features` | List or submit roadmap feature requests | Public & internal voting |
| `GET\|PUT\|DELETE` | `/features/{id}` | Retrieve, update, or delete feature request | UUID `external_id` |
| `GET\|POST` | `/monitors` | List or register HTTP uptime/SSL monitors | Health checks & status |
| `GET\|PUT\|DELETE` | `/monitors/{id}` | Retrieve, update, or delete monitor | UUID `external_id` |
| `POST` | `/automations/sync-lead-stages` | Run automated lead pipeline stage sync | Supports dry-run & filter |
| `POST` | `/automations/generate-playbook-tasks` | Generate institutional sales playbook tasks | Personalized outreach |
| `GET` | `/system/health` | Run system diagnostic check and inspect alerts | Health status & alerts |

---

## 3. Query Parameters & Standards

### Pagination
All collection endpoints support pagination:
- `?page=1` (1-indexed page number)
- `?per_page=25` (defaults to 25, capped at 100)

### Sorting
Pass `?sort=field` for ascending or `?sort=-field` for descending:
```
GET /crm/api/v2/leads?sort=-amount
GET /crm/api/v2/tasks?sort=due_at
```

### External IDs
All resource URLs strictly bind to UUID `external_id`, never database integer primary keys:
```
GET /crm/api/v2/leads/9c836932-b7b5-4a64-b0f3-8b74a3821a8d
```

---

## 4. Automation Endpoints

### Run Lead Stage Sync
```http
POST /crm/api/v2/automations/sync-lead-stages
Content-Type: application/json

{
  "lead_id": "9c836932-b7b5-4a64-b0f3-8b74a3821a8d",
  "dry_run": false
}
```

Response:
```json
{
  "success": true,
  "dry_run": false,
  "total_tasks_processed": 14,
  "leads_synced_count": 3,
  "synced_leads": [
    {
      "id": "9c836932-b7b5-4a64-b0f3-8b74a3821a8d",
      "title": "Alpha Hedge - Quant Pipeline",
      "stage": "Meeting Scheduled",
      "status": "Lead"
    }
  ]
}
```

### System Health
```http
GET /crm/api/v2/system/health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2026-10-08T13:45:00+00:00",
  "alerts_count": 0,
  "alerts": []
}
```
