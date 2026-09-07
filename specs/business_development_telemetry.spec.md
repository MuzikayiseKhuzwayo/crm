# Specification: Business Development Telemetry & Commercial Intelligence Infrastructure

**Spec ID:** `SPEC-BD-001`  
**Standard Compliance:** `dubstrata-systems` (`SYS-001`, `SYS-002`, `SYS-003`, Anti-Mirage Protocol)  
**Lifecycle:** SDD Plan Execution  

---

## 1. Intent & Scope

### Problem Statement
Commercial development (deal-making, distribution channel architecture, joint ventures, and strategic contracts) currently lacks structured telemetry, stage-gate margin protection, and cross-functional operational handoff integrity. Without standardized contract telemetry and automated handoffs:
- Enterprise deals risk over-promising custom engineering and violating SLAs.
- Pipeline metrics fail to isolate commercial velocity across the Ansoff Matrix (Market Penetration, Product Development, Market Development, Diversification).
- Channel partner contribution, discount compression, and anchor client network effects remain unmeasured.

### In-Scope
1. **Contract Telemetry & CRM Schema Extension**:
   - Tracking standardized contract attributes (contract length, payment terms, custom SLA commitments, partner rev-share %, escalation clauses, minimum usage commitments, referenceability flags).
2. **Operational Clearance Stage-Gates**:
   - Enforcing formal clearance sign-offs across **Product** (capability verification), **Operations** (delivery capacity), and **Finance** (margin discipline) before deals advance to won/signed.
3. **Derisking Deal Playbook**:
   - Capturing the narrative arc progression: **Problem $\rightarrow$ Pitfalls $\rightarrow$ Unique Insight $\rightarrow$ Execution** on deal records.
4. **Ansoff Matrix & Pirate Metrics (AARRR) Telemetry**:
   - Ingesting, aggregating, and reporting on the 20 core commercial data points across direct sales, product co-design, channel architecture, and diversification.
5. **Defensive Boundary Normalization (22P02 Immunity)**:
   - Ensuring deterministic UUID conversion (`ensureValidUuid()`) and safe nullable fallback (`ensureNullableUuid()`) at all API/event ingestion boundaries.
6. **Knowledge Graph Triplet Extraction**:
   - Synchronizing commercial entities (Organizations, Champions, Deals, Partners, Products) and generating structural claims (`SOURCED`, `CHAMPIONED`, `UNLOCKED`, `INTRODUCED_TO`).
7. **Live Datastore Write Verification**:
   - Ensuring that all telemetry dispatches result in verified, persisted rows in destination datastore tables.

### Out-of-Scope
- General host application billing gateway / payment processing (handled by host app or Stripe integration).
- Legacy v1 CRM controllers and obsolete audit logging traits.

---

## 2. Domain & Data Contracts

### 2.1 Database Tables & Models

#### 1. `crm_contracts`
- `id` (bigint, PK)
- `external_id` (uuid, unique, indexed)
- `team_id` (foreignId, nullable)
- `deal_id` (foreignId, constrained)
- `organization_id` (foreignId, nullable)
- `contract_type` (enum: `direct`, `pilot`, `enterprise`, `channel`, `joint_venture`, `amendment`)
- `term_months` (integer, default 12)
- `payment_terms` (string, e.g. `NET_30`, `NET_60`, `ANNUAL_PREPAID`)
- `sla_commitment_level` (enum: `standard`, `gold`, `mission_critical`, `bespoke`)
- `sla_penalty_clause` (boolean, default false)
- `partner_rev_share_percent` (decimal 5,2, default 0.00)
- `annual_price_escalation_percent` (decimal 5,2, default 0.00)
- `minimum_commitment_amount` (bigint, default 0 - integer cents)
- `bespoke_work_ratio` (decimal 5,2, default 0.00)
- `is_referenceable` (boolean, default false)
- `is_design_partner` (boolean, default false)
- `kickoff_at` (timestamp, nullable)
- `signed_at` (timestamp, nullable)
- `renewed_at` (timestamp, nullable)
- `renewal_status` (enum: `pending`, `renewed_flat`, `renewed_expansion`, `churned`, `renegotiated_down`)
- `timestamps` & `softDeletes`

#### 2. `crm_handoff_gates`
- `id` (bigint, PK)
- `external_id` (uuid, unique, indexed)
- `team_id` (foreignId, nullable)
- `deal_id` (foreignId, constrained)
- `gate_type` (enum: `product_capability`, `operational_capacity`, `financial_margin`, `legal_compliance`)
- `status` (enum: `pending`, `approved`, `rejected`, `waived`)
- `cleared_by_user_id` (foreignId, nullable)
- `cleared_at` (timestamp, nullable)
- `rejection_reason` (text, nullable)
- `metadata` (json, nullable)
- `timestamps`

#### 3. `crm_partner_profiles`
- `id` (bigint, PK)
- `external_id` (uuid, unique, indexed)
- `team_id` (foreignId, nullable)
- `organization_id` (foreignId, constrained)
- `partner_tier` (enum: `si`, `var`, `distributor`, `referral`, `technology`)
- `status` (enum: `prospect`, `onboarding`, `active`, `dormant`, `churned`)
- `region_code` (string, nullable)
- `recruited_at` (timestamp, nullable)
- `first_sale_at` (timestamp, nullable)
- `last_deal_at` (timestamp, nullable)
- `total_sourced_pipeline_amount` (bigint, default 0)
- `timestamps` & `softDeletes`

#### 4. `crm_deal_derisking`
- `id` (bigint, PK)
- `external_id` (uuid, unique, indexed)
- `team_id` (foreignId, nullable)
- `deal_id` (foreignId, constrained)
- `competitor_id` (foreignId, nullable)
- `problem_statement` (text, nullable)
- `pitfalls_identified` (text, nullable)
- `unique_insight` (text, nullable)
- `execution_plan` (text, nullable)
- `commercial_thesis_validated` (boolean, nullable)
- `loi_signed_at` (timestamp, nullable)
- `pilot_converted_at` (timestamp, nullable)
- `timestamps`

#### 5. `crm_telemetry_events`
- `id` (bigint, PK)
- `external_id` (uuid, unique, indexed)
- `team_id` (foreignId, nullable)
- `event_name` (string, indexed)
- `entity_type` (string)
- `entity_id` (uuid, indexed)
- `ansoff_quadrant` (enum: `market_penetration`, `product_development`, `market_development`, `diversification`)
- `pirate_stage` (enum: `acquisition`, `activation`, `retention`, `revenue`, `referral`)
- `metric_key` (string, indexed)
- `metric_value` (decimal 15,4, default 0)
- `payload` (json)
- `recorded_at` (timestamp, indexed)
- `timestamps`

#### 6. `processing_performance_logs` (Dubstrata SYS-001 Checkpoint)
- `id` (bigint, PK)
- `external_id` (uuid, unique)
- `trace_id` (string, indexed)
- `stage` (enum: `ingestion`, `validation`, `gate_check`, `db_write`, `graph_sync`)
- `latency_ms` (decimal 10,3)
- `status` (string)
- `metadata` (json, nullable)
- `created_at` (timestamp)

---

## 3. Business Logic & Invariants

1. **Stage-Gate Locking Rule**:
   - A `Deal` cannot transition to pipeline stages marked with `is_closed_won = true` unless:
     - `Product` gate is approved (or explicitly waived with audit trail).
     - `Operations` gate is approved (or explicitly waived with audit trail).
     - `Finance` gate is approved (or explicitly waived with audit trail).
2. **22P02 Boundary Normalization Rule**:
   - Ingestion methods MUST normalize identifiers using `UuidNormalizer::ensureValidUuid()` or `UuidNormalizer::ensureNullableUuid()`.
   - String hashes (e.g. email, guest ID, external legacy IDs) must be converted into deterministic RFC 4122 v5 UUIDs rather than allowing un-normalized strings into UUID columns.
3. **Idempotency Invariant**:
   - Ingestion endpoints must support `X-Idempotency-Key` or payload SHA-256 deduplication via `x402_transactions` pattern to guarantee at-most-once processing of stage transitions.
4. **Knowledge Graph Triplet Extraction**:
   - Whenever an anchor customer closes a deal, or a channel partner sources a deal, triplet extraction emits graph claims:
     - `(:Organization {id}) -[:UNLOCKED]-> (:Deal {id})`
     - `(:Partner {id}) -[:SOURCED]-> (:Deal {id})`
     - `(:Person {is_champion: true}) -[:CHAMPIONS]-> (:Deal {id})`

---

## 4. Error Handling & Edge Cases

- **PostgreSQL 22P02 Prevention**: Graceful mapping of non-standard UUID strings to deterministic v5 UUIDs with warnings logged to `processing_performance_logs`.
- **Missing Gate Rejection**: Deals attempting to bypass gates receive `HandoffGateIncompleteException` with a list of blocking gates and responsible stakeholders.
- **Concurrent Ingestion**: Deduplication by idempotency key with immediate 200/202 return of cached response.
- **Telemetry Circuit Breaker**: If graph sync fails, the relational datastore commit succeeds, and the graph event is dispatched to a background retry queue.

---

## 5. Technical Constraints

- Native integration with Laravel CRM v2 Livewire components (`src/Livewire/Deals/DealShow.php`, `DealEdit.php`).
- High performance logging conforming to `dubstrata-systems` low-latency specifications.
- Fully compatible with PHP 8.2-8.4, Laravel 11-13, and SQLite in-memory testing.
