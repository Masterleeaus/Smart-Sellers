# Chatbot Vertical Templates — Pass 2

## Purpose

Pass 2 turns the vertical catalogue from a set of generic shell presets into composable business experiences inside the existing Chatbot PWA shell.

No separate PWA, local database, outbox, sync engine or WorkCore write authority is introduced.

## Template layers

The shared shell now supports three composable template layers:

1. **Vertical presets** — industry language, navigation, roles and capability mix.
2. **Functional templates** — Booking, E-commerce Store, Hire and Rental, Accommodation.
3. **WorkCore workspace templates** — CRM, Projects and Jobs, Finance, Crew and Team.

Canonical Titan platform apps remain separate from these template layers. A role preset selects which platform surface should present the resolved template stack:

- Titan Zero — owner/manager administration
- Titan Go — field, crew and fulfilment work
- Titan Desk — reception, scheduling and seller/service desk work
- Titan Hub — customer/guest/member self service

## Functional templates

### Booking

Reusable for services, appointments, classes, events, reservations and constrained capacity.

Offline users may browse downloaded offerings, prepare a request and queue changes. A scarce worker, room, asset, seat or other resource is not final until WorkCore confirms it.

### E-commerce Store

Reusable product catalogue, cart, checkout-intent, order and return experience.

The local cart may remain fully usable offline. Final stock allocation, payment settlement and order acceptance remain authoritative server operations.

### Hire and Rental

Reusable rental catalogue, date-range availability, commercial hire request, pickup/return and condition experience.

WorkCore remains authoritative for the physical asset, custody, maintenance and final availability.

### Accommodation

Reusable property/space discovery, rate snapshot, stay and reservation experience.

Cached availability is advisory offline. WorkCore remains authoritative for occupancy and reservation confirmation.

## Vertical composition

| Vertical | Functional templates |
| --- | --- |
| Field and Home Services | Booking, Hire/Rental |
| BnB, Hotel and Rooming Services | Accommodation, Booking |
| Real Estate | Booking |
| Salons and Personal Care | Booking, E-commerce |
| Fitness and Membership | Booking, E-commerce |
| Automotive Services | Booking, E-commerce, Hire/Rental |
| E-commerce and Retail | E-commerce |
| Hire and Rental | Hire/Rental, E-commerce, Booking |
| Booking, Reservation and Capacity-Based | Booking |

Every vertical also composes the existing WorkCore workspaces:

- CRM
- Projects and Jobs
- Crew and Team
- Finance

The role presets narrow that full capability set for individual users.

## Role-aware examples

### Field and Home Services

- Owner → Titan Zero + Jobs/Projects, CRM, Crew/Team, Finance
- Dispatcher → Titan Desk + Booking, Jobs/Projects, Crew/Team
- Field worker → Titan Go + Jobs/Projects, Crew/Team
- Customer → Titan Hub + Booking

### Accommodation

- Manager → Titan Zero + WorkCore workspaces + Accommodation/Booking
- Front desk → Titan Desk + Accommodation, Booking, CRM, Finance
- Housekeeping → Titan Go + Jobs/Projects, Crew/Team
- Guest/customer → Titan Hub + Accommodation

### Automotive

- Service manager → Titan Zero + WorkCore workspaces + Booking/Store/Hire
- Service advisor → Titan Desk + Booking, CRM, Store
- Technician → Titan Go + Jobs/Projects, Crew/Team
- Customer → Titan Hub + Booking and Store

## Terminology overlays

Verticals now supply semantic labels consumed by the shell and future generative UI layers, including:

- customer
- work item
- worker
- location
- offering
- reservation

Examples:

- Salon: Client / Appointment / Stylist or practitioner / Salon / Treatment / Appointment
- Fitness: Member / Session / Coach / Studio / Class or session / Booking
- Accommodation: Guest / Stay / Staff member / Property / Room or stay / Reservation
- Hire: Hirer / Hire / Team member / Depot / Asset / Reservation

These labels alter presentation, not domain authority or table ownership.

## Registry and API

`TitanRegistry` now:

- registers functional templates;
- exposes `functional()` catalogue summaries;
- normalises functional-template, terminology and role-preset metadata;
- resolves a vertical with `compose($verticalSlug, $role)`;
- validates references to functional/workspace templates;
- exposes role presets and terminology in catalogue summaries.

The Titan template API now returns:

- `vertical_templates`
- `functional_templates`
- `workspace_templates`
- role presets and terminology
- resolved composition for a vertical

Installation can pass a `role` so the resulting configuration resolves the appropriate Titan platform surface and template subset.

## Shared-shell and offline boundary

All templates continue to use the existing Chatbot edge runtime.

They do **not** create:

- a second IndexedDB database;
- a second outbox;
- a second sync protocol;
- a second conflict engine;
- a second customer identity authority;
- a second scheduling, inventory, accommodation or accounting authority.

Offline requests can be drafted and queued. Server-authoritative state is reconciled through the shared sync/conflict system.

## WorkCore boundary

WorkCore remains the structured operational authority. Vertical templates describe presentation and allowed capability composition; they do not duplicate WorkCore business logic.

## Verification

The dedicated `Chatbot Vertical Templates` workflow validates:

- all Titan catalogue and schema JSON;
- the original nine-vertical/four-workspace contract;
- the four functional templates;
- vertical-to-functional composition;
- required terminology;
- role presets;
- registry/API composition hooks;
- PHP syntax for the registry, schema and controller.

## Next pass

1. Wire the resolved role/vertical composition into the Chatbot builder selector and live shell preview.
2. Bind ChatbotEcommerce capability manifests to Booking/Store/Hire/Accommodation functional templates.
3. Produce capability-filtered offline packs per resolved composition.
4. Add vertical onboarding questions and default automation/agent packs.
5. Add functional templates for Scheduling/Dispatch, Assets/Inventory, Compliance/Quality and Management dashboards.
