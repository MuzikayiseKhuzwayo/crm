# Implementation Tasks: Business Development Telemetry & Commercial Intelligence Infrastructure

**Spec Reference:** `specs/business_development_telemetry.spec.md`  
**Standard Compliance:** `dubstrata-systems` (`SYS-001`, `SYS-002`, `SYS-003`, Anti-Mirage Protocol)  

---

- [x] **Task 1: [Schema & Data Layer — Telemetry, Contracts, Stage-Gates & Partners]**
  - **Target Files:**
    - `database/migrations/2026_09_07_000001_create_crm_business_development_tables.php`
    - `src/Models/Contract.php`
    - `src/Models/HandoffGate.php`
    - `src/Models/PartnerProfile.php`
    - `src/Models/DealDerisking.php`
    - `src/Models/TelemetryEvent.php`
    - `src/Models/ProcessingPerformanceLog.php`
  - **Objective:** Implement domain models and database tables for contract telemetry (`crm_contracts`), operational clearance gates (`crm_handoff_gates`), partner network profiles (`crm_partner_profiles`), narrative de-risking playbooks (`crm_deal_derisking`), partitioned telemetry events (`crm_telemetry_events`), and ingestion performance logs (`processing_performance_logs`).
  - **Verification:** Execute migration and model tests verifying schema types, relationships, table prefixes, and soft-delete behaviors.

- [x] **Task 2: [Defensive Boundary Normalization & Ingestion Gateway (22P02 Immunity)]**
  - **Target Files:**
    - `src/Support/UuidNormalizer.php`
    - `src/Services/Telemetry/TelemetryIngestionService.php`
    - `src/Http/Middleware/NormalizeTelemetryBoundary.php`
    - `tests/Unit/UuidNormalizerTest.php`
  - **Objective:** Enforce deterministic UUID normalization (`ensureValidUuid()`) and safe nullable fallback (`ensureNullableUuid()`) to immunize SQL layers against PostgreSQL `22P02` invalid text representation exceptions. Wire SHA-256 payload idempotency and log ingestion latency into `processing_performance_logs`.
  - **Verification:** Run `vendor/bin/phpunit --filter UuidNormalizerTest` verifying robust normalization across guest IDs, legacy integer IDs, and invalid UUID strings.

- [x] **Task 3: [Commercial Stage-Gate & De-Risking Domain Logic]**
  - **Target Files:**
    - `src/Services/BusinessDevelopment/HandoffGateService.php`
    - `src/Services/BusinessDevelopment/DeriskingPlaybookService.php`
    - `src/Services/BusinessDevelopment/ContractTelemetryService.php`
    - `src/Observers/DealTelemetryObserver.php`
    - `tests/Feature/BusinessDevelopment/HandoffGateEnforcementTest.php`
  - **Objective:** Implement domain business rules enforcing Product, Operations, and Financial clearance before a Deal moves to Won/Signed. Implement Derisking narrative state machine (Problem $\rightarrow$ Pitfall $\rightarrow$ Unique Insight $\rightarrow$ Execution) and contract telemetry calculators (SQO velocity, Latency, Escalation, Net Realized Margin).
  - **Verification:** Run `vendor/bin/phpunit --filter HandoffGateEnforcementTest` verifying blocked stage transitions when gates are pending.

- [x] **Task 4: [Commercial Knowledge Graph Triplet Extractor & Synthesis]**
  - **Target Files:**
    - `src/Services/Graph/TripletExtractor.php`
    - `src/Services/Graph/CommercialGraphService.php`
    - `tests/Feature/BusinessDevelopment/CommercialGraphTest.php`
  - **Objective:** Extract and persist knowledge graph entities (`Organization`, `Person`, `Deal`, `Partner`) and structural relationship claims (`SOURCED`, `CHAMPIONED`, `UNLOCKED`, `INTRODUCED_TO`) enabling graph traversal of anchor client influence and champion stability.
  - **Verification:** Run `vendor/bin/phpunit --filter CommercialGraphTest` verifying triplet generation and claim relationship extraction.

- [x] **Task 5: [Presentation & Livewire Operator Interfaces]**
  - **Target Files:**
    - `src/Livewire/BusinessDevelopment/HandoffGateModal.php`
    - `src/Livewire/BusinessDevelopment/DeriskingPlaybookWidget.php`
    - `src/Livewire/BusinessDevelopment/ContractTelemetryCard.php`
    - `src/Livewire/BusinessDevelopment/CommercialIntelligenceDashboard.php`
    - `resources/views/livewire/business-development/handoff-gate-modal.blade.php`
    - `resources/views/livewire/business-development/derisking-playbook-widget.blade.php`
    - `resources/views/livewire/business-development/contract-telemetry-card.blade.php`
    - `resources/views/livewire/business-development/commercial-intelligence-dashboard.blade.php`
  - **Objective:** Register and build reactive Livewire components for stage-gate clearances, derisking narrative checklists, contract telemetry attributes, and executive commercial intelligence dashboards across Ansoff quadrants.
  - **Verification:** Render tests asserting Livewire component mount, gate clearance event handling, and dashboard metric displays.

- [x] **Task 6: [Live Runtime Wiring & Datastore Persistence Assertion (Anti-Mirage Protocol)]**
  - **Target Files:**
    - `tests/Feature/BusinessDevelopment/LiveTelemetryPersistenceTest.php`
  - **Objective:** Mandatory Anti-Mirage task: Mount runtime telemetry handlers, dispatch full end-to-end commercial operations (deal progression, gate signoff, partner deal registration, milestone clearance), and assert non-zero row counts and exact attribute integrity in destination datastores (`crm_contracts`, `crm_handoff_gates`, `crm_deal_derisking`, `crm_telemetry_events`, `processing_performance_logs`).
  - **Verification:** Execute `vendor/bin/phpunit --filter LiveTelemetryPersistenceTest` verifying live relational row persistence and non-empty datastore state.
