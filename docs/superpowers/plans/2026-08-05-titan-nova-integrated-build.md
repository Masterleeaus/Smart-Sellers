# Titan Nova Integrated Build Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Titan Nova as a BlogPilot-derived Laravel extension, expose its daily operator workflow through the existing Chatbot PWA shell, connect operational records and writes to WorkCore, and integrate the existing Flutter customer hub once its source is present.

**Architecture:** Titan Nova owns venture creation concepts—opportunities, ventures, genomes, missions, experiments, simulations, decision gates and portfolio learning. WorkCore remains authoritative for CRM, customers, bookings, jobs, schedules, workforce, invoices and payments. The Chatbot extension hosts the operator PWA through the existing Titan template system; Flutter consumes the same versioned backend API and never owns business rules.

**Tech Stack:** PHP 8.2+, Laravel 10, MagicAI extension architecture, Blade, Chatbot Titan App templates, WorkCore governed actions/read models, Flutter when present.

## Global Constraints

- Existing pages and classes are donors: duplicate the closest matching file, rename it fully, then edit it.
- Do not introduce a second customer, booking, job, workforce, invoice or payment authority.
- New database tables are permitted where Titan Nova owns an independent lifecycle.
- All writes crossing into WorkCore use governed actions with tenant, actor, causation, correlation and idempotency context.
- The operator PWA is part of `app/extensions/Chatbot`; do not create a separate PWA application.
- Flutter changes must be made only after a real `pubspec.yaml` and customer-hub donor screens are present.

---

### Task 1: Stabilise the Laravel extension foundation

**Files:**
- Existing generated extension: `app/extensions/TitanNova/**`
- Modify: `app/Domains/Marketplace/MarketplaceServiceProvider.php`
- Test: `app/extensions/TitanNova/tests/titan_nova_verify.php`

**Interfaces:**
- Consumes: BlogPilot folder architecture, assets, views, scheduling and analytics patterns.
- Produces: installable `titan-nova` extension with renamed provider, routes, models, commands and tables.

- [x] Duplicate BlogPilot into `TitanNova` while preserving its asset and view layout.
- [x] Rename namespace, provider, controller, models, commands, routes, tables and visible labels.
- [x] Replace WordPress publishing with generic channel execution.
- [x] Register `TitanNovaServiceProvider` in the marketplace provider map.
- [x] Run JSON validation, PHP syntax checks and the extension verifier.

### Task 2: Repair and extend the Chatbot PWA template registry

**Files:**
- Donor: `app/extensions/Chatbot/resources/titan-apps/TemplateSchemas/titan-zero.json`
- Modify: `app/extensions/Chatbot/resources/titan-apps/TemplateSchemas/index.json`
- Modify: `app/extensions/Chatbot/resources/titan-apps/TemplateSchemas/legacy-index-v1.json`
- Create by duplicating donor: `app/extensions/Chatbot/resources/titan-apps/TemplateSchemas/titan-nova-operator.json`

**Interfaces:**
- Consumes: Chatbot Titan app template schema and WorkCoreAppBridge conventions.
- Produces: operator PWA navigation, offline records, permissions, read models and command declarations.

- [x] Replace the conflicted template index with the valid legacy registry.
- [x] Duplicate the Titan Zero template into Titan Nova Operator.
- [x] Add Inbox, Bookings, Missions and Approvals primary navigation.
- [x] Add replies, bookings, tasks, ventures, pilots, training, payments and analytics surfaces.
- [x] Declare WorkCore domains, read models, commands, offline packs and permissions.

### Task 3: Add Titan Nova venture-domain records

**Files:**
- Duplicate model donor: `app/extensions/TitanNova/System/Models/TitanNovaAgent.php`
- Duplicate task donor: `app/extensions/TitanNova/System/Models/TitanNovaTask.php`
- Duplicate migration donors under `app/extensions/TitanNova/database/migrations/`
- Extend: `app/extensions/TitanNova/System/TitanNovaServiceProvider.php`
- Extend: `app/extensions/TitanNova/System/Http/Controllers/TitanNovaController.php`

**Interfaces:**
- Produces models and tables for `TitanNovaOpportunity`, `TitanNovaVenture`, `TitanNovaVerticalGenome`, `TitanNovaMission`, `TitanNovaExperiment`, `TitanNovaSimulation`, `TitanNovaDecisionGate`, `TitanNovaChannel`, `TitanNovaConversation`, `TitanNovaOutcome`, `TitanNovaEvidence` and `TitanNovaActionReceipt`.

- [ ] Duplicate existing Eloquent model and migration patterns for every Nova-owned lifecycle.
- [ ] Add tenant/company keys, public IDs, status, provenance and timestamps consistently.
- [ ] Add relationships from agents and tasks to ventures and missions.
- [ ] Add explicit state-transition methods and reject invalid transitions.
- [ ] Add policies and request validation by duplicating the nearest existing files.
- [ ] Extend the verifier with table, relation and transition checks.

### Task 4: Connect WorkCore operational read models

**Files:**
- Donor pattern: `docs/WORKCORE_INTEGRATION_PATTERN.md`
- Donor runtime: `app/extensions/Chatbot/System/TitanAI/WorkCoreApps/**`
- Add by duplicating nearest integration services under `app/extensions/TitanNova/System/Integration/WorkCore/`

**Interfaces:**
- Consumes WorkCore CRM, WorkOperations, Commercial, PropertyOperations and WorkforceAssurance query contracts.
- Produces tenant-scoped Nova read models for leads, bookings, jobs, workforce readiness, invoices, payments and service outcomes.

- [ ] Duplicate the closest WorkCore query-service donor for each required domain.
- [ ] Rename services and capabilities to Titan Nova terminology.
- [ ] Apply tenant context and company authorization to every query.
- [ ] Expose read-only venture views without copying WorkCore records into Nova tables.
- [ ] Add cache policies and freshness metadata.
- [ ] Test tenant isolation and permission denial.

### Task 5: Connect governed WorkCore writes

**Interfaces:**
- Consumes the host governed action dispatcher.
- Produces action receipts linked to tasks, missions and ventures.

- [ ] Map Nova commands to WorkCore actions for lead promotion, booking changes, job creation, scheduling, invoice creation, payment requests and evidence capture.
- [ ] Require approval for high-risk, financial, customer-contact and destructive actions.
- [ ] Include idempotency, causation and correlation IDs.
- [ ] Store the returned receipt without duplicating WorkCore authority.
- [ ] Add retry and compensation metadata for failed actions.

### Task 6: Build operator reply and booking workflows in Chatbot

**Files:**
- Duplicate existing Chatbot app pages/components closest to inbox, booking and approval workflows.
- Extend the `titan-nova-operator` template and WorkCore app bridge mappings.

- [ ] Add unified reply queue backed by channel conversations.
- [ ] Add booking detail, reschedule and cancellation-request actions.
- [ ] Add mission/task attention list and approvals.
- [ ] Add offline drafts and conflict handling.
- [ ] Add push notifications for replies, booking changes and approvals.
- [ ] Verify mobile, tablet, offline, syncing, conflict and empty states.

### Task 7: Add the customer-hub Flutter integration

**Current blocker:** The branch inventory found zero `pubspec.yaml` files. No Flutter files can be safely changed until the mobile app source is committed or connected.

**Expected files once present:**
- Duplicate the closest existing booking, payment, message, invoice and profile screens.
- Duplicate the existing API client/repository/state-management patterns.

- [ ] Identify the actual customer-hub Flutter project and architecture.
- [ ] Duplicate existing screens for bookings, payments, quotes, invoices, messages and service history.
- [ ] Rename duplicated classes/files to Titan Hub customer terminology.
- [ ] Connect only to versioned Laravel APIs; do not embed business rules in Flutter.
- [ ] Add native push, deep links, secure storage and payment-return handling using existing app patterns.
- [ ] Run Flutter analyze, tests and platform builds.

### Task 8: Add simulations, training and venture evolution

- [ ] Add opportunity evidence and capability-isomorphism analysis.
- [ ] Add Vertical Genome versions and compiler outputs.
- [ ] Add simulation runs and 1,000-job scenario results.
- [ ] Add curriculum, competency, cohorts and readiness gates.
- [ ] Add pilot mission control and outcome capture.
- [ ] Add genome mutations, canary rollout and rollback.
- [ ] Add portfolio scale, licence, sell or retire decisions.

### Task 9: Verification and release

- [ ] Run all Titan Nova PHP tests and host integration tests.
- [ ] Validate every JSON template and extension manifest.
- [ ] Run tenant-isolation, authorization, idempotency and receipt tests.
- [ ] Verify the Chatbot operator PWA through its existing browser test path.
- [ ] Run Flutter tests only after its source is present.
- [ ] Produce install/upgrade documentation and a migration rollback plan.
