# WorkCore Vertical Dashboards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the generic WorkCore home experience with nine governed, tenant-scoped Titan Zero vertical dashboards assembled from reusable WorkCore domain widgets, while retaining CRM, Operations, Workforce, Resources and Commercial as drill-down workspaces and AI Governance as an administrator-only surface.

**Architecture:** The parent WorkCore extension owns the vertical-dashboard registry, resolver, composition schema, HTTP routes and presentation components. Canonical WorkCore packages continue to own their records, calculations and read models; they expose versioned dashboard widgets through registered providers. A company selects one primary vertical and may enable additional verticals per business line, producing tabs that share filters and terminology without duplicating business data.

**Tech Stack:** PHP 8.2, Laravel 10, Blade, existing WorkCore `ReadModelRegistry` and `ReadModelExecutor`, tenant/permission/entitlement contracts, existing vertical catalogue and terminology resolver, MagicAI `<x-layouts.app>`, PHPUnit/Pest-compatible feature tests, Python architecture tests.

## Global Constraints

- Do not create an executive or universal management dashboard.
- Do not add dashboard-owned business tables or copy canonical records into a dashboard database.
- Preserve extension folders, namespaces, route families, capability keys, migration history and canonical table ownership.
- All dashboard queries must be scoped to the active `company_id`; business-line, branch, territory, property and location filters must narrow that scope.
- Cross-extension reads must use registered read models, contracts or stable query services. Cross-extension writes must use governed actions or domain events.
- Missing tenant context, permission, capability or entitlement must fail closed.
- The primary vertical must be resolved server-side from an authorised company setting; request parameters cannot activate an unentitled vertical.
- A company may enable multiple verticals. One is primary; the others appear as authorised tabs.
- CRM, Operations, Workforce, Resources and Commercial remain canonical drill-down workspaces and reusable widget engines.
- AI Governance is administrator-only and must never appear as a customer vertical.
- Dashboard responses must be versioned, deterministic and free of fabricated values.
- Desktop, tablet, mobile and Titan Flow consume the same response contract with different presentation density.
- Realtime refresh is limited to dispatch, attendance, urgent compliance, active bookings, asset availability and critical finance exceptions.
- Cache keys must include company, user access scope, permission revision, entitlement revision, vertical, business line and normalized filters.
- No new frontend framework is introduced until the contracts and read models are stable.

---

## Approved Vertical Catalogue

| Key | Customer label | Business-type entries |
|---|---|---:|
| `field-services` | Field and Home Services | 24 |
| `accommodation` | BnB, Hotel and Rooming Services | 22 |
| `real-estate` | Real Estate | 20 |
| `salons-personal-care` | Salons and Personal Care | 20 |
| `fitness-membership` | Fitness and Membership Businesses | 22 |
| `automotive-services` | Automotive Services | 23 |
| `ecommerce-retail` | E-commerce and Retail | 24 |
| `hire-rental` | Hire and Rental | 22 |
| `booking-capacity` | Booking, Reservation and Capacity-Based Businesses | 28 |

The catalogue must contain exactly **9 verticals and 205 business-type entries**. Business-type labels are copied from the approved Titan Zero coverage list; normalized slugs are unique within their vertical.

## Required Dashboard Keys

```text
workcore.dashboard.vertical.field_services
workcore.dashboard.vertical.accommodation
workcore.dashboard.vertical.real_estate
workcore.dashboard.vertical.salons_personal_care
workcore.dashboard.vertical.fitness_membership
workcore.dashboard.vertical.automotive_services
workcore.dashboard.vertical.ecommerce_retail
workcore.dashboard.vertical.hire_rental
workcore.dashboard.vertical.booking_capacity
workcore.dashboard.ai_governance
```

The legacy executive key is forbidden by architecture tests and must not be registered.

## Shared Response Contract

```php
array{
    schema_version: string,
    dashboard_key: string,
    vertical_key: string,
    company_id: int,
    business_line_public_id: string|null,
    generated_at: string,
    as_of: string,
    tabs: list<array{key: string, label: string, active: bool, route: string}>,
    filters: array<string,mixed>,
    kpis: list<array{
        key: string,
        label: string,
        value: int|float|string|null,
        unit: string|null,
        severity: 'neutral'|'info'|'warning'|'critical'|null,
        comparison: array{value: int|float|null, direction: 'up'|'down'|'flat'|null, label: string|null}|null,
        drilldown: array{route: string, params: array<string,mixed>}|null
    }>,
    widgets: list<array{
        key: string,
        provider: string,
        component: string,
        title: string,
        size: 'small'|'medium'|'large'|'full',
        refresh_seconds: int|null,
        data: array<string,mixed>,
        unavailable_reason: string|null,
        drilldown: array{route: string, params: array<string,mixed>}|null
    }>,
    alerts: list<array{
        key: string,
        severity: 'info'|'warning'|'critical',
        title: string,
        message: string,
        count: int|null,
        action: array{route: string, params: array<string,mixed>}|null
    }>,
    freshness: array{
        source_keys: list<string>,
        oldest_source_at: string|null,
        stale: bool,
        stale_after_seconds: int
    }
}
```

## Shared Domain Engines

| Engine | Canonical responsibility |
|---|---|
| Customer & CRM | Leads, customers, contacts, activities, pipelines, reviews and support |
| Scheduling & Capacity | Appointments, calendars, rooms, seats, staff, assets and availability |
| Operations & Dispatch | Jobs, assignments, routes, work status, recurring work and exceptions |
| Workforce | Workers, rosters, availability, attendance, credentials and payroll signals |
| Property & Resources | Premises, rooms, vehicles, assets, equipment, custody and maintenance |
| Inventory & Supply | Stock, reservations, materials, suppliers and purchase orders |
| Commercial | Quotes, invoices, payments, expenses, deposits, profitability and reconciliation |
| Compliance & Assurance | Inspections, hazards, incidents, credentials, corrective actions and evidence |
| Membership & Agreements | Memberships, subscriptions, leases, service agreements, hires and waivers |

---

### Task 1: Vertical Dashboard Catalogue and Contracts

**Files:**
- Create: `app/extensions/WorkCore/System/VerticalDashboards/VerticalDashboardDefinition.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/VerticalDashboardRegistry.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/VerticalDashboardResponse.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/VerticalDashboardFilter.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/VerticalWidgetDefinition.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Modify: `app/extensions/WorkCore/System/WorkCoreServiceProvider.php`
- Test: `app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_catalogue.py`

**Interfaces:**
- Consumes: existing WorkCore vertical catalogue, terminology resolver, capability registry and entitlement resolver.
- Produces: `VerticalDashboardRegistry::get(string $key): ?VerticalDashboardDefinition` and `VerticalDashboardRegistry::all(): array`.

- [ ] **Step 1: Write the failing catalogue test**

Assert nine verticals, 205 business-type entries, unique dashboard keys, unique vertical keys, unique business-type slugs within each vertical, complete route/capability/widget metadata and absence of any executive dashboard registration.

- [ ] **Step 2: Run the test and verify failure**

```bash
python -m unittest app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_catalogue.py -v
```

- [ ] **Step 3: Implement immutable definitions**

```php
[
    'field-services' => 'Field and Home Services',
    'accommodation' => 'BnB, Hotel and Rooming Services',
    'real-estate' => 'Real Estate',
    'salons-personal-care' => 'Salons and Personal Care',
    'fitness-membership' => 'Fitness and Membership Businesses',
    'automotive-services' => 'Automotive Services',
    'ecommerce-retail' => 'E-commerce and Retail',
    'hire-rental' => 'Hire and Rental',
    'booking-capacity' => 'Booking, Reservation and Capacity-Based Businesses',
]
```

Each definition contains `key`, `label`, `readModel`, `routeName`, `capabilities`, `businessTypes`, `widgets`, `defaultPeriod`, `realtimeChannels` and `terminologyProfile`.

- [ ] **Step 4: Copy all approved business types into the definitions**

Preserve the approved labels. Generate lowercase kebab-case slugs and reject duplicates during registry construction.

- [ ] **Step 5: Normalize filters**

Accept only `as_of`, `period_from`, `period_to`, `business_line_public_id`, `branch_public_id`, `territory_public_id`, `location_public_id`, `property_public_id`, `worker_public_id`, `resource_public_id`, `currency` and `timezone`. Discard unknown keys. Normalize company-local dates to UTC for queries.

- [ ] **Step 6: Run tests and commit**

```bash
python -m unittest app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_catalogue.py -v
php app/extensions/WorkCore_Platform/tools/verify_workcore_entitlement_projection.php
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_catalogue.py
git commit -m "feat(workcore): add Titan Zero vertical dashboard catalogue"
```

---

### Task 2: Primary Vertical Resolver and Multi-Vertical Tabs

**Files:**
- Create: `app/extensions/WorkCore/System/VerticalDashboards/CompanyVerticalSelection.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/CompanyVerticalResolver.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/VerticalTabBuilder.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Exceptions/NoAccessibleVertical.php`
- Modify: `app/extensions/WorkCore/System/Navigation/WorkCoreWorkspaceManifestBuilder.php`
- Test: `app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_resolver.py`

**Interfaces:**
- Produces: `CompanyVerticalResolver::resolve(int $companyId, int $userId, ?string $businessLinePublicId): CompanyVerticalSelection`.

- [ ] **Step 1: Write failing resolver tests**

Cover single-vertical defaulting, configured primary selection, inaccessible-primary fallback, unentitled request rejection, ordered secondary tabs, business-line override, no-access failure and exclusion of AI Governance from customer tabs.

- [ ] **Step 2: Implement resolution order**

```text
1. authorised business-line primary vertical
2. authorised company primary vertical
3. first authorised enabled vertical using administrator order
4. fail closed
```

- [ ] **Step 3: Build authorised tab metadata**

Each tab returns key, translated label, active state and route. Omit tabs lacking enabled capabilities.

- [ ] **Step 4: Run tests and commit**

```bash
python -m unittest app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_resolver.py -v
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_resolver.py
git commit -m "feat(workcore): resolve primary and secondary vertical dashboards"
```

---

### Task 3: Widget Provider Registry and Vertical Composer

**Files:**
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Contracts/DashboardWidgetProvider.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/DashboardWidgetRegistry.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/VerticalDashboardComposer.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/DashboardCacheKey.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Widgets/CrmWidgetProvider.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Widgets/OperationsWidgetProvider.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Widgets/WorkforceWidgetProvider.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Widgets/ResourcesWidgetProvider.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Widgets/CommercialWidgetProvider.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Widgets/AssuranceWidgetProvider.php`
- Test: `app/extensions/WorkCore_Platform/tests/test_workcore_vertical_widget_composition.py`

**Interfaces:**

```php
interface DashboardWidgetProvider
{
    public function key(): string;
    public function supports(VerticalWidgetDefinition $widget): bool;
    public function load(
        VerticalWidgetDefinition $widget,
        CompanyVerticalSelection $selection,
        VerticalDashboardFilter $filter
    ): array;
}
```

- [ ] **Step 1: Write failing composition tests**

Assert deterministic widget order, tenant propagation, capability filtering, unavailable-widget reasons, duplicate-provider rejection, response validation and cache isolation.

- [ ] **Step 2: Implement provider registry and composer**

The composer resolves the selected vertical, filters widgets by entitlement/permission/access level, calls providers through registered contracts, marks optional missing capabilities as unavailable, fails only when a required core capability is absent, and emits tabs, KPIs, widgets, alerts and freshness metadata.

- [ ] **Step 3: Implement cache-key isolation**

Include company, user, access revision, permission revision, entitlement revision, vertical, business line and normalized filters.

- [ ] **Step 4: Run tests and commit**

```bash
python -m unittest app/extensions/WorkCore_Platform/tests/test_workcore_vertical_widget_composition.py -v
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/tests/test_workcore_vertical_widget_composition.py
git commit -m "feat(workcore): compose vertical dashboards from domain widgets"
```

---

### Task 4: Vertical Dashboard Routes and Presentation

**Files:**
- Create: `app/extensions/WorkCore/System/Http/Controllers/VerticalDashboardController.php`
- Create: `app/extensions/WorkCore/System/Http/Resources/VerticalDashboardResource.php`
- Create: `app/extensions/WorkCore/resources/views/vertical-dashboard.blade.php`
- Create: `app/extensions/WorkCore/resources/views/components/vertical-dashboard/tabs.blade.php`
- Create: `app/extensions/WorkCore/resources/views/components/vertical-dashboard/kpi-card.blade.php`
- Create: `app/extensions/WorkCore/resources/views/components/vertical-dashboard/widget.blade.php`
- Create: `app/extensions/WorkCore/resources/views/components/vertical-dashboard/alert-list.blade.php`
- Modify: `app/extensions/WorkCore/routes/web.php`
- Modify: `app/extensions/WorkCore/routes/api.php`
- Test: `app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_routes.py`

**Routes:**

```php
Route::get('/dashboard/workcore', [VerticalDashboardController::class, 'default'])
    ->name('dashboard.user.workcore.vertical.default');
Route::get('/dashboard/workcore/vertical/{vertical}', [VerticalDashboardController::class, 'show'])
    ->name('dashboard.user.workcore.vertical.show');
```

The authenticated API route is `GET /api/v1/workcore/vertical-dashboards/{vertical}`.

- [ ] **Step 1: Test default resolution, authorised switching, unknown/inaccessible keys, tenant failure, API schema, stale state and empty state**
- [ ] **Step 2: Implement routes and reusable Blade components with loading, empty, partial, stale and error states**
- [ ] **Step 3: Keep CRM, Operations, Workforce, Resources and Commercial as operational drilldowns linked from widgets**
- [ ] **Step 4: Run tests and commit**

```bash
python -m unittest app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_routes.py -v
php -l app/extensions/WorkCore/System/Http/Controllers/VerticalDashboardController.php
php -l app/extensions/WorkCore/System/Http/Resources/VerticalDashboardResource.php
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_routes.py
git commit -m "feat(workcore): add vertical dashboard routes and views"
```

---

### Task 5: Field and Home Services Dashboard Pack

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/src/Domains/WorkCore/System/Modules/Operations/ReadModels/GetFieldServicesOperationsSnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/src/Domains/WorkCore/System/Modules/Operations/Providers/WorkOperationsServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/tests/Feature/Dashboards/GetFieldServicesOperationsSnapshotTest.php`

**Metrics:** jobs booked/dispatched/completed today, unassigned/overdue/emergency jobs, available workers, on-time arrival, first-time completion, technician utilisation, callbacks, jobs awaiting invoice and compliance-blocked jobs.

**Widgets:** today job board, dispatch/route map, unassigned and overdue queue, worker capacity, materials/equipment, site access/compliance, completion evidence, invoice readiness, callbacks/warranty and customer communications.

- [ ] **Step 1: Write tenant-isolation and metric tests**
- [ ] **Step 2: Implement bounded snapshot using dispatch-board status, assignment, worker, premises and date-window semantics**
- [ ] **Step 3: Register Operations and Workforce as required; CRM, Resources, Inventory, Commercial and Assurance as optional**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetFieldServicesOperationsSnapshotTest
python app/extensions/WorkCore_Platform/tools/validate_repository.py --repo app/extensions/WorkCore_Platform
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-work-operations
git commit -m "feat(workcore): add field services dashboard pack"
```

---

### Task 6: Accommodation Dashboard Pack

**Files:**
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/src/Domains/WorkCore/System/Modules/Premises/Application/Accommodation/ReadModels/GetAccommodationBoard.php`
- Create: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/src/Domains/WorkCore/System/Modules/Premises/Application/Accommodation/ReadModels/GetAccommodationDashboardSnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/src/Domains/WorkCore/System/Verticals/Providers/WorkCoreVerticalOperationsServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/tests/Feature/Dashboards/GetAccommodationDashboardSnapshotTest.php`

**Metrics:** occupancy, arrivals, departures, in-house guests, available/dirty/blocked rooms, open housekeeping, late turnovers, maintenance-blocked nights, outstanding balances and average stay.

**Widgets:** occupancy board, arrivals/departures, check-in/out queue, housekeeping, room readiness, maintenance blocks, guest requests/incidents, folio exceptions, linen/supply and rooming agreements/notices.

- [ ] **Step 1: Test tenant isolation and all metrics**
- [ ] **Step 2: Implement using canonical reservations, stays, rooms, housekeeping and premises readiness**
- [ ] **Step 3: Confirm no parallel accommodation tables are introduced**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetAccommodationDashboardSnapshotTest
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-property-operations
git commit -m "feat(workcore): add accommodation dashboard pack"
```

---

### Task 7: Real Estate Dashboard Pack

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/src/Domains/WorkCore/System/Modules/Premises/Application/RealEstate/ReadModels/GetRealEstatePortfolioSnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/src/Domains/WorkCore/System/Verticals/Providers/WorkCoreVerticalOperationsServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/tests/Feature/Dashboards/GetRealEstatePortfolioSnapshotTest.php`

**Metrics:** properties managed, occupied/vacant properties, average vacancy days, pending applications, inspections due, expiring leases, overdue receivables, open maintenance, compliance blocks, sales pipeline and owner/tenant requests.

**Widgets:** portfolio position, vacancy/leasing, sales pipeline, applications, inspections, agreement expiry, owner/tenant requests, maintenance coordination, property compliance, profitability and agent workload.

- [ ] **Step 1: Test tenant isolation and metrics**
- [ ] **Step 2: Implement with premises, agreements, applications, visits, inspections, maintenance and CRM read models**
- [ ] **Step 3: Reuse premises readiness for access, service-window, plan and key blockers**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetRealEstatePortfolioSnapshotTest
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-property-operations
git commit -m "feat(workcore): add real estate dashboard pack"
```

---

### Task 8: Salons and Personal Care Dashboard Pack

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/src/Domains/WorkCore/System/Modules/Scheduling/ReadModels/GetSalonCapacitySnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/src/Domains/WorkCore/System/Modules/Operations/Providers/WorkOperationsServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/tests/Feature/Dashboards/GetSalonCapacitySnapshotTest.php`

**Metrics:** appointments, available slots, late arrivals, no-shows, waitlist, appointment/staff utilisation, rebooking, client spend, retail attachment, package balances and unpaid balances.

**Widgets:** appointment book, availability gaps, staff/room capacity, walk-ins/waitlist, rebooking, memberships/packages, retail stock, commissions, deposits/balances and service recovery.

- [ ] **Step 1: Test metrics, tenant isolation and terminology**
- [ ] **Step 2: Implement capacity read model**
- [ ] **Step 3: Render resource terminology as chair, room, practitioner station or treatment room from the business-type profile**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetSalonCapacitySnapshotTest
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-work-operations
git commit -m "feat(workcore): add salons and personal care dashboard pack"
```

---

### Task 9: Fitness and Membership Dashboard Pack

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-business-network/src/Domains/WorkCore/System/Modules/Memberships/ReadModels/GetFitnessMembershipSnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-business-network/src/Domains/WorkCore/System/Providers/WorkCoreBusinessNetworkServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-business-network/tests/Feature/Dashboards/GetFitnessMembershipSnapshotTest.php`

**Metrics:** active/new/cancelled members, net growth, churn risk, recurring revenue, revenue per member, class utilisation, waitlists, check-in frequency, trial conversion, failed payments and expiries.

**Widgets:** membership position, growth/churn, class capacity, trainer coverage, attendance, engagement, trials/leads, payment failures, expiries, facility issues and staff credentials.

- [ ] **Step 1: Test metrics and tenant isolation**
- [ ] **Step 2: Implement membership snapshot**
- [ ] **Step 3: When membership or recurring billing is unavailable, preserve schedule/attendance widgets and return explicit unavailable reasons**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetFitnessMembershipSnapshotTest
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-business-network
git commit -m "feat(workcore): add fitness and membership dashboard pack"
```

---

### Task 10: Automotive Services Dashboard Pack

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/src/Domains/WorkCore/System/Modules/Repairs/ReadModels/GetAutomotiveWorkshopSnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/src/Domains/WorkCore/System/Modules/Operations/Providers/WorkOperationsServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/tests/Feature/Dashboards/GetAutomotiveWorkshopSnapshotTest.php`

**Metrics:** vehicles booked, awaiting diagnosis, quotes awaiting approval, jobs awaiting parts, work in progress, ready for collection, bay utilisation, technician productivity, labour recovery, repair-order value, comeback rate and fleet services due.

**Widgets:** workshop board, arrivals, diagnosis, approvals, parts delays, technician/bay allocation, roadworthy inspections, collection queue, parts/supplier ETA, warranty/comebacks, fleet maintenance and towing/roadside.

- [ ] **Step 1: Test metrics and tenant isolation**
- [ ] **Step 2: Implement workshop snapshot and authorised drilldowns using public identifiers**
- [ ] **Step 3: Verify registration, VIN and customer details never cross access scope**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetAutomotiveWorkshopSnapshotTest
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-work-operations
git commit -m "feat(workcore): add automotive services dashboard pack"
```

---

### Task 11: E-commerce and Retail Dashboard Pack

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-commercial/src/Domains/WorkCore/System/Modules/Commerce/ReadModels/GetRetailCommerceSnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-commercial/src/Domains/WorkCore/System/Modules/Finance/WorkCoreFinanceServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-commercial/tests/Feature/Dashboards/GetRetailCommerceSnapshotTest.php`

**Metrics:** gross/net sales, orders, average order value, margin, payment/fulfilment queues, returns, refunds, stockouts, low stock, repeat purchasing and subscription churn.

**Widgets:** sales, fulfilment, click-and-collect, returns/refunds, product/channel/location performance, inventory, purchase orders/supplier delays, abandoned carts, subscriptions, support, profitability and reconciliation.

- [ ] **Step 1: Test metrics and tenant isolation**
- [ ] **Step 2: Implement using canonical catalogue, inventory, supply, order, invoice and Titan Money authorities**
- [ ] **Step 3: Verify no dashboard order or inventory tables are introduced**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetRetailCommerceSnapshotTest
php tests/Architecture/verify_magicai_workcore_extraction.php
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-commercial
git commit -m "feat(workcore): add ecommerce and retail dashboard pack"
```

---

### Task 12: Hire and Rental Dashboard Pack

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/src/Domains/WorkCore/System/Modules/Assets/Application/Hire/ReadModels/GetHireRentalSnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/src/Domains/WorkCore/System/Verticals/Providers/WorkCoreVerticalOperationsServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-property-operations/tests/Feature/Dashboards/GetHireRentalSnapshotTest.php`

**Metrics:** available/reserved/on-hire assets, returns due, overdue returns, preparation queue, maintenance blocks, utilisation, revenue per asset, hire duration, damage rate and deposit exposure.

**Widgets:** availability calendar, reservations, preparation/dispatch, pickups/deliveries, due/overdue returns, on-hire assets, condition/damage, turnaround, maintenance, deposits/usage charges, contracts/waivers, location and profitability.

- [ ] **Step 1: Test metrics and tenant isolation**
- [ ] **Step 2: Implement using canonical asset, availability, reservation, maintenance and commercial rules**
- [ ] **Step 3: Verify availability is calculated through reservation-conflict rules rather than a status column alone**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetHireRentalSnapshotTest
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-property-operations
git commit -m "feat(workcore): add hire and rental dashboard pack"
```

---

### Task 13: Booking, Reservation and Capacity Dashboard Pack

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/src/Domains/WorkCore/System/Modules/Scheduling/ReadModels/GetBookingCapacitySnapshot.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/src/Domains/WorkCore/System/Modules/Operations/Providers/WorkOperationsServiceProvider.php`
- Modify: `app/extensions/WorkCore/System/VerticalDashboards/vertical-dashboard-definitions.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-work-operations/tests/Feature/Dashboards/GetBookingCapacitySnapshotTest.php`

**Metrics:** available/booked capacity, utilisation, bookings, waitlist, conflicts, cancellations, no-shows, revenue per slot, booking value, deposit collection and repeat booking.

**Widgets:** booking calendar, capacity timeline, available slots, waitlist, conflicts, staff/resource availability, arrivals/check-ins, cancellations/no-shows, deposits/balances, group/recurring bookings, preparation, source performance and forecast.

- [ ] **Step 1: Test metrics, conflicts, tenant isolation and terminology**
- [ ] **Step 2: Implement capacity snapshot**
- [ ] **Step 3: Render capacity as appointments, seats, rooms, courts, vehicles, equipment, tables or places from the terminology profile**
- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=GetBookingCapacitySnapshotTest
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-work-operations
git commit -m "feat(workcore): add booking and capacity dashboard pack"
```

---

### Task 14: AI Operations and Governance Admin Surface

**Files:**
- Create: `app/extensions/WorkCore_Platform/packages/workcore-business-network/src/Domains/WorkCore/System/AI/ReadModels/GetAiGovernanceDashboard.php`
- Modify: `app/extensions/WorkCore_Platform/packages/workcore-business-network/src/Domains/WorkCore/System/Providers/WorkCoreBusinessNetworkServiceProvider.php`
- Create: `app/extensions/WorkCore/System/Http/Controllers/AiGovernanceDashboardController.php`
- Modify: `app/extensions/WorkCore/routes/web.php`
- Modify: `app/extensions/WorkCore/routes/api.php`
- Test: `app/extensions/WorkCore_Platform/packages/workcore-business-network/tests/Feature/AI/GetAiGovernanceDashboardTest.php`
- Test: `app/extensions/WorkCore_Platform/tests/test_workcore_ai_governance_routes.py`

**Metrics:** pending/overdue approvals, active/failed agent runs, failed tool runs, retries, overrides, reversals, usage, estimated cost, high-risk actions, active policies and outbox failures.

**Widgets:** approval queue, run health, tool failures, override/reversal log, usage/cost, model-provider-agent breakdown, policy adoption, high-risk actions and outbox/audit failures.

- [ ] **Step 1: Test administrator access, ordinary-user denial, cross-company isolation and absence from vertical tabs**
- [ ] **Step 2: Implement read model `workcore.dashboard.ai_governance` and admin-only routes**
- [ ] **Step 3: Run tests and commit**

```bash
php artisan test --filter=GetAiGovernanceDashboardTest
python -m unittest app/extensions/WorkCore_Platform/tests/test_workcore_ai_governance_routes.py -v
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/packages/workcore-business-network
git commit -m "feat(workcore): add administrator AI governance dashboard"
```

---

### Task 15: Multi-Surface Projection, Performance and Accessibility

**Files:**
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Projection/DashboardProjection.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Projection/WorkspaceProjection.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Projection/TabletProjection.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Projection/MobileProjection.php`
- Create: `app/extensions/WorkCore/System/VerticalDashboards/Projection/TitanFlowProjection.php`
- Create: `app/extensions/WorkCore/resources/views/vertical-dashboard-mobile.blade.php`
- Create: `app/extensions/WorkCore/resources/views/vertical-dashboard-tablet.blade.php`
- Test: `app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_projection.py`
- Test: `app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_performance.py`

- [ ] **Step 1: Test that all surfaces retain dashboard/company/tabs/alerts/freshness and never add unauthorised data**
- [ ] **Step 2: Make mobile prioritise urgent alerts, today queues and next actions; tablet retain boards/maps; workspace retain the complete set; Titan Flow expose concise cards and governed actions**
- [ ] **Step 3: Enforce budgets: cached p95 <= 250 ms, uncached p95 <= 1500 ms, initial payload <= 250 KB, list widgets <= 100 rows and map widgets <= 500 points**
- [ ] **Step 4: Add labelled controls, keyboard tabs, non-colour severity labels and semantic headings**
- [ ] **Step 5: Run tests and commit**

```bash
python -m unittest app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_projection.py -v
python -m unittest app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_performance.py -v
git add app/extensions/WorkCore app/extensions/WorkCore_Platform/tests
git commit -m "feat(workcore): project vertical dashboards across Titan surfaces"
```

---

### Task 16: Final Architecture Verification and Documentation

**Files:**
- Create: `app/extensions/WorkCore_Platform/tests/test_workcore_vertical_dashboard_architecture.py`
- Modify: `app/extensions/WorkCore_Platform/README.md`
- Modify: `app/extensions/WorkCore_Platform/native-extensions/catalogue.json`
- Modify: `app/extensions/WorkCore_Platform/docs/superpowers/specs/2026-08-04-workcore-navigation-workspaces-design.md`

- [ ] **Step 1: Assert exactly nine customer vertical dashboards and 205 approved business types**
- [ ] **Step 2: Assert no universal executive dashboard definition, route, read model or navigation entry exists**
- [ ] **Step 3: Assert AI Governance is admin-only, every widget source and drilldown is registered, all read models enforce tenant context, packages do not import another package's Eloquent models, no dashboard business tables exist and five domain workspaces remain available**
- [ ] **Step 4: Run verification**

```bash
python -m unittest discover app/extensions/WorkCore_Platform/tests -p "test_workcore_vertical_dashboard*.py" -v
php artisan test --filter=Dashboard
python app/extensions/WorkCore_Platform/tools/validate_repository.py --repo app/extensions/WorkCore_Platform
php app/extensions/WorkCore_Platform/tools/verify_workcore_entitlement_projection.php
php tests/Architecture/verify_magicai_workcore_extraction.php
php tests/Standalone/WorkCoreExtraction/run.php
```

- [ ] **Step 5: Scan runtime and customer-facing documentation for the removed executive architecture**

The verification test performs this scan and fails when the legacy dashboard key or removed branding appears in runtime definitions, routes, tests or customer-facing navigation.

- [ ] **Step 6: Document the nine verticals, 205 business types, resolver, shared engines, canonical ownership, routes, AI Governance and surface projections**
- [ ] **Step 7: Commit**

```bash
git add app/extensions/WorkCore app/extensions/WorkCore_Platform
git commit -m "docs(workcore): finalize vertical dashboard architecture"
```

---

## Recommended Pull Request Sequence

1. **Vertical foundation** — Tasks 1–3
2. **Routes and reusable presentation** — Task 4
3. **Field services** — Task 5
4. **Accommodation and real estate** — Tasks 6–7
5. **Salons and fitness** — Tasks 8–9
6. **Automotive** — Task 10
7. **E-commerce and retail** — Task 11
8. **Hire and booking** — Tasks 12–13
9. **AI Governance** — Task 14
10. **Multi-surface projection and final verification** — Tasks 15–16

Each pull request must be independently deployable, preserve existing routes and data, include tenant-isolation tests, and make unavailable optional widgets explicit rather than fabricating metrics.

## Completion Criteria

- Every authorised company resolves to one primary vertical dashboard.
- Multi-vertical companies can switch among authorised tabs.
- All nine approved verticals and 205 business types are represented.
- The five WorkCore domain workspaces remain operational drilldowns.
- AI Governance is restricted to authorised administrators.
- No universal executive dashboard key, route or customer navigation entry exists.
- Every metric derives from canonical tenant-scoped records.
- Desktop, tablet, mobile and Titan Flow use the same response semantics.
- Targeted tests and repository verification commands pass.
