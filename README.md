![Smart Sellers - one catalogue, three seller-side AI roles, every sales channel](app/extensions/ChatbotEcommerce/docs/assets/smart-sellers-header.svg)

# Smart Sellers

**One governed commerce system for sellers who need to sell, operate, and support a catalogue across channels.**

## Overview

Smart Sellers gives a seller one source of truth for products, variants, prices, availability, orders, customer context, and policy. Three coordinated roles then use that shared foundation: a shopping assistant for customers, a commerce steward for seller operations, and a communications agent for post-purchase support.


## Measured evidence

The quickest provider-free role/authority lane is split into two standalone checks:

| Check | Deterministic checks | What it covers |
| --- | ---: | --- |
| Three-role primitives | **12** | role routing, self-elevation rejection, confidence/identity decisions, refund limits, human-only fraud/legal paths, fail-closed unknown actions |
| Three-role contract checks | **82** | required files, communication tables, runtime methods, identity gating, controller/provider wiring, routes, configuration, manifests, OpenAPI and tool-service wiring |

Reproduce:

```bash
php app/extensions/ChatbotEcommerce/tests/run_three_role_primitives.php
php app/extensions/ChatbotEcommerce/tests/run_three_role_contract_checks.php
```

The commerce engine also contains payment, marketplace, inventory and order-workbench checks plus PHPUnit feature/unit coverage. These focused scripts verify the role and authority contracts without needing marketplace, payment or model credentials.

## What is new

Smart Sellers' technical signature is **three AI-facing roles over one commerce truth with different authority ceilings**.

```text
Shared seller commerce state
       ↓
Role router
  ┌────┼─────────────┐
  ↓    ↓             ↓
Shop  Seller       Customer
assist steward     communications
  └────┼─────────────┘
       ↓
Authority / approval / escalation
       ↓
Commerce action journal + durable state
```

The useful distinction is not simply “three agents.” Customers cannot self-elevate into seller authority, private order/payment context is withheld until identity verification, refund actions respect explicit limits, and fraud/legal paths remain human-only.

## Product problem

Commerce becomes expensive to operate when the catalogue, marketplace listings, inventory, orders, and customer conversations drift into separate systems. Smart Sellers brings those workflows behind one seller-owned commerce boundary so the seller can:

- present products, services, hire/rental offerings, and bookable capacity from one catalogue;
- keep inventory, pricing, availability, orders, and fulfilment context connected;
- prepare marketplace and support actions with role-specific permissions and review;
- give customers useful answers grounded in the seller's own commerce state.

## Verified capabilities

| Role | Implemented responsibility |
| --- | --- |
| **Customer Shopping Assistant** | Product discovery, recommendations, option and availability checks, cart building, and purchase guidance in customer-facing contexts. |
| **Seller Commerce Steward** | Catalogue quality, listing preparation, marketplace operations, inventory signals, order monitoring, and recommended seller actions. |
| **Customer Communications Agent** | Order-aware replies, delivery and feedback follow-up, policy-governed support actions, and escalation when confidence or authority is insufficient. |

The roles share the same commerce context but do not share unrestricted authority. `CommerceRoleRouter` selects a role from the interaction context, while `CustomerCommunicationAuthority` evaluates identity, confidence, amount, and action policy before sensitive support work can execute.

## Architecture

Smart Sellers is a Laravel/PHP extension built around a durable commerce core rather than a chat interface placed beside an existing store:

```text
Seller catalogue + policies
          |
          v
Commerce context: products, services, hire, bookings,
inventory, availability, carts, orders, payments, fulfilment
          |
          +--> Customer Shopping Assistant
          +--> Seller Commerce Steward
          +--> Customer Communications Agent
          |
          v
Seller store, marketplaces, support channels, and reviewable actions
```

The design keeps seller authority with the commerce application. AI-assisted work can prepare or recommend an action, while permissions, approval tokens, idempotency, action journals, rollback records, and audit history provide the durable boundary for consequential changes.

## Implemented commerce capabilities

- Catalogue records with variants, SKUs, content, categories, pricing, and channel listings.
- Carts, checkout sessions, orders, returns, payment operations, webhooks, shipping, tax, and fulfilment foundations.
- Inventory locations, reservations, stock adjustments, channel allocation, reconciliation, and conflict queues.
- Marketplace connections with listing reads, order import, governed writes, dry runs, rate-limit handling, and partial rollback paths.
- Customer communication threads, order-aware response drafting, support actions, human handoff, and escalation.
- Services, appointments, hire/rental periods, deposits, extensions, bookings, reservations, and capacity-oriented offerings.

Provider integrations and live readiness depend on the configured host and external credentials; the presence of a contract or adapter is not by itself a production result.

## Source map

| Area | Entry points |
| --- | --- |
| Extension wiring | [`extension.manifest.json`](app/extensions/ChatbotEcommerce/extension.manifest.json), [`ChatbotEcommerceServiceProvider.php`](app/extensions/ChatbotEcommerce/System/ChatbotEcommerceServiceProvider.php) |
| Role resolution | [`CommerceRoleRuntime.php`](app/extensions/ChatbotEcommerce/System/Services/CommerceRoleRuntime.php), [`CommerceRoleRouter.php`](app/extensions/ChatbotEcommerce/System/Support/CommerceRoleRouter.php) |
| Support authority | [`CustomerCommunicationAuthority.php`](app/extensions/ChatbotEcommerce/System/Support/CustomerCommunicationAuthority.php) |
| Commerce tools | [`EcommerceToolService.php`](app/extensions/ChatbotEcommerce/System/Services/EcommerceToolService.php), [`MarketplaceToolRuntime.php`](app/extensions/ChatbotEcommerce/System/Services/MarketplaceToolRuntime.php), [`OrderWorkbenchToolRuntime.php`](app/extensions/ChatbotEcommerce/System/Services/OrderWorkbenchToolRuntime.php) |
| Durable commerce state | [`database/migrations`](app/extensions/ChatbotEcommerce/database/migrations), models, queues, and scheduled lifecycle jobs |
| Architecture notes | [`ChatbotEcommerce/OPERATIONS.md`](app/extensions/ChatbotEcommerce/docs/OPERATIONS.md), [`document-index.json`](docs/document-index.json), [`extension-inventory.json`](docs/extension-inventory.json) |

## Evidence and focused verification

The repository contains standalone primitive and contract checks for role routing, support authority, extension wiring, payments, marketplace behavior, inventory, and order workbench boundaries. The quickest source-backed checks are:

```bash
php app/extensions/ChatbotEcommerce/tests/run_three_role_primitives.php
php app/extensions/ChatbotEcommerce/tests/run_three_role_contract_checks.php
php extensions/chatbot-ecommerce/tests/run_payment_primitives.php
```

The commerce extension also contains PHPUnit feature and unit coverage. Run the full suite inside a compatible Laravel host with the repository's Composer/Pest configuration; this repository is an extension bundle, not a standalone host application.

## Extension boundary

Smart Sellers focuses on seller-owned commerce: catalogue, inventory, offers, bookings, orders, payments, fulfilment, marketplaces, and customer communication. Shared host infrastructure is used where required, while unrelated creative-generation tools, unrelated vertical engines, and general-purpose provider libraries are outside this product boundary.

## Setup

Smart Sellers targets the Laravel/PHP stack declared by `app/extensions/ChatbotEcommerce/extension.manifest.json`. Install it through a compatible host, then configure the host's Composer, database, queues, channels, and provider credentials. There is no root `.env.example` or standalone application bootstrap in this repository.

## Honest scope

The checked-in code demonstrates the commerce extension's contracts, role routing, policies, durable state, and focused tests. It does not by itself prove live marketplace writes, payment settlement, model-provider quality, every supported channel, or multi-tenant production operation. Those require a compatible host, provider-specific fixtures, credentials, and deployment validation.

Historical and pending-integration material under `docs/legacy-root/` and related archive areas is retained for provenance. It is not presented as active runtime capability.

## License and third-party terms

Review the repository's applicable license and the terms of each marketplace, payment, shipping, tax, AI, and messaging provider before deployment or redistribution.

