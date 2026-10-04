![Smart Sellers - one catalogue, three seller-side AI roles, every sales channel](app/extensions/ChatbotEcommerce/docs/assets/smart-sellers-header.svg)

# Smart Sellers

**The AI-powered commerce system that takes a seller's product line wherever customers discover, compare and buy.**

Smart Sellers gives a seller one source of truth for products, variants, prices, availability, orders and customer policies. Three coordinated AI roles use that shared commerce foundation to sell the catalogue, operate its sales channels and support customers.

The product is designed for physical goods, digital products, services, hire and rentals, and bookable offerings. A seller can combine product sales with appointments, reservations, rental periods, deposits and extensions in one commerce operation.

## Three AI roles for one seller

| AI role | What it does |
|---|---|
| **Customer Shopping Assistant** | Acts as the seller's always-available sales representative. It answers questions from the seller's catalogue, recommends suitable products, checks options and availability, builds carts, and guides customers to purchase. It can be surfaced on the seller's website and other supported customer-facing channels. |
| **Seller Commerce Steward** | Manages the seller's product line across connected marketplaces. It prepares and improves listings, publishes approved changes, monitors sales and stock, imports orders, detects channel conflicts, and recommends next actions. |
| **Customer Communications Agent** | Handles enquiries before and after purchase. It follows up on orders, requests feedback, drafts replies using recorded order and fulfilment facts, prepares policy-governed support actions, and escalates cases that need a person. |

All three roles share the same catalogue, inventory, bookings, orders, customer context and seller-defined policies. They act with role-specific permissions and keep consequential seller changes reviewable.

## One commerce system for varied offerings

Smart Sellers is intended to support more than conventional product checkout:

- **Products:** variants, categories, bundles, content, channel listings and stock.
- **Services:** service options, availability, appointments, deposits and customer follow-up.
- **Hire and rental:** rental periods, availability, agreements, deposits, extensions, returns and charges.
- **Bookings and reservations:** capacity, time slots, confirmations, changes, reminders and cancellation rules.
- **Mixed catalogues:** physical products, services and bookable or rentable offerings can be presented through the same seller-owned commerce experience.

This makes the platform suitable for merchants whose offering combines goods with installation, appointments, equipment hire, events, accommodation or other capacity-based services.

## Commerce capabilities

### Catalogue and listing operations

- Central product records with variants, SKUs, descriptions, images, categories and pricing.
- Channel-specific listing content linked to a canonical seller catalogue.
- Listing quality and marketplace compliance checks.
- Brand voice profiles and evidence-aware content suggestions.
- Draft, preview, approve, publish and rollback flows for marketplace changes.
- Bulk listing operations with dry runs, rate-limit governance and partial rollback.

### Storefront and assisted selling

- A seller-owned digital store and customer shopping experience.
- Embedded or channel-surfaced shopping assistant, subject to each destination's integration rules.
- Product discovery, conversational questions, recommendations, cart and checkout.
- Customer-specific context, preferences and budget controls.
- Product, service, hire and booking availability presented in a consistent experience.

### Marketplace sales and inventory

- Marketplace connection and credential management.
- Marketplace search, listing reads, order import and governed listing writes.
- Inventory locations, stock adjustments, reservations and channel allocation.
- Inventory reconciliation and conflict queues.
- Unified order views across native storefront and connected marketplaces.
- Sales and order exceptions surfaced for seller action.

### Orders, payments and fulfilment

- Cart and checkout sessions, orders, order events and returns.
- Payment intents, payment operations, refunds, webhook processing and BNPL structures.
- Shipping methods, quotes, zones, fulfilment and tax configuration.
- Settlement entries, reconciliation and order exception management.
- Rental agreements, charges, payments, receipts and ledger records.

Payment, shipping, tax and marketplace capabilities depend on configured providers and verified integrations. The presence of an adapter or API does not by itself mean every provider is production-ready.

### Customer communications

- Customer communication threads across supported channels.
- Order-aware response drafting grounded in recorded commerce state.
- Follow-up for order progress, delivery, feedback and service recovery.
- Prepared support actions such as returns, cancellations, refunds, address corrections, replacements and store credit.
- Approval policies, action history and human handoff for sensitive or uncertain cases.

### Seller control and safety

- Seller, shopper and support roles with separate tool permissions.
- Tenant-scoped commerce data and credential vault patterns.
- Approval tokens for governed actions.
- Idempotency, action journals, rollback records and audit history.
- Queue-based marketplace reads, writes, inventory reconciliation and payment webhooks.
- Automation configured by action and policy, with review where required.

## Architecture

<p align="center"><img src="app/extensions/ChatbotEcommerce/docs/assets/smart-sellers-architecture.svg" alt="Smart Sellers architecture infographic: seller catalogue, three AI roles, shared commerce core, connected channels and seller authority controls" width="100%"></p>

```text
Seller catalogue and policies
             �
       Shared commerce core
       ��� Products, services, hire and bookings
       ��� Inventory, availability and pricing
       ��� Carts, orders, payments and fulfilment
       ��� Customer context and communication history
             �
       Three seller-side AI roles
       ��� Customer Shopping Assistant
       ��� Seller Commerce Steward
       ��� Customer Communications Agent
             �
       Seller store and supported channels
```

The existing extension is built as a Laravel/PHP module with service providers, APIs, persistence models, jobs, queues, scheduled lifecycle tasks, marketplace and payment provider contracts, and a capability manifest. The extension currently lives at `app/extensions/ChatbotEcommerce/`; **Smart Sellers** is the product name for the seller-owned commerce system.

## Repository implementation

The codebase already contains meaningful commerce foundations, including catalogue and variant records, carts and checkout sessions, inventory reservations, pricing rules, orders and returns, marketplace connections and listing proposals, customer communication threads and actions, and a unified order workbench.

This README describes the intended integrated product. It does not claim that every listed workflow, marketplace, payment provider, storefront surface or AI role has passed end-to-end production verification. Treat the repository's tests, provider contracts and deployment configuration as the evidence for each released capability.

### What is actually implemented

The three-role slice is a governed application runtime, not a claim that three independent autonomous models are bundled here. `CommerceRoleRouter` resolves a customer storefront or support context to the shopping or communications role and routes authenticated sellers to the seller steward. `CustomerCommunicationAuthority` applies confidence, identity, amount and action rules before a support action can execute. The host chatbot/AI runtime remains responsible for model-provider orchestration; this extension exposes the commerce context, tools, policies and durable action boundary.

Useful source locations:

- [`extension.manifest.json`](app/extensions/ChatbotEcommerce/extension.manifest.json) - compatibility, routes, permissions, owned tables, queues and lifecycle declarations.
- [`ChatbotEcommerceServiceProvider.php`](app/extensions/ChatbotEcommerce/System/ChatbotEcommerceServiceProvider.php) - route, migration, command and scheduler wiring.
- [`CommerceRoleRuntime.php`](app/extensions/ChatbotEcommerce/System/Services/CommerceRoleRuntime.php), [`CommerceRoleRouter.php`](app/extensions/ChatbotEcommerce/System/Support/CommerceRoleRouter.php) and [`CustomerCommunicationAuthority.php`](app/extensions/ChatbotEcommerce/System/Support/CustomerCommunicationAuthority.php) - role resolution and authority decisions.
- [`EcommerceToolService.php`](app/extensions/ChatbotEcommerce/System/Services/EcommerceToolService.php), [`MarketplaceToolRuntime.php`](app/extensions/ChatbotEcommerce/System/Services/MarketplaceToolRuntime.php) and [`OrderWorkbenchToolRuntime.php`](app/extensions/ChatbotEcommerce/System/Services/OrderWorkbenchToolRuntime.php) - the tool-facing commerce boundary.
- [`database/migrations`](app/extensions/ChatbotEcommerce/database/migrations) - tenant-scoped commerce state, action journals, communication records and booking capacity.

### Focused verification quickstart

These checks are intentionally host-independent and exercise the role router, support authority matrix and extension wiring:

```bash
php app/extensions/ChatbotEcommerce/tests/run_three_role_primitives.php
php app/extensions/ChatbotEcommerce/tests/run_three_role_contract_checks.php
```

The extension also contains 38 standalone `run_*` primitive/contract scripts, 14 PHPUnit feature files and unit/conformance tests. A full host verification uses the repository's Composer/Pest installation and should be run only after configuring a compatible Laravel host; this checkout does not include a root `.env.example`, and external provider credentials are not test fixtures.

The strongest repository evidence is therefore source-level contract coverage plus the focused scripts above. It is not evidence of live marketplace, payment, model-provider or multi-tenant production operation.

## Product boundary

Smart Sellers focuses on helping a seller present, sell and support the seller's own offerings across channels. General-purpose AI provider libraries, unrelated creative-generation tools, unrelated business vertical engines and non-commerce extension suites are outside the product boundary. Shared infrastructure remains in scope when the commerce system depends on it.

## Development

The extension targets the Laravel/PHP stack declared in its manifest. Use the repository's Composer, Node, test and CI configuration as the source of truth for setup and validation.

Before release, verify the extension in a clean host installation, including tenant isolation, role permissions, checkout and payment provider flows, marketplace reads and writes, inventory concurrency, support actions, queues, migrations, rollback and uninstall behaviour.

The repository also contains extensive design, migration and historical material under `docs/`, including `docs/legacy-root/` and archived TitanAI plans. Those files are retained as provenance and pending-integration context; they are not treated as proof that the corresponding feature is active in the Smart Sellers runtime.

## License and third-party terms

Review the repository's applicable license and the terms of each marketplace, payment, shipping, tax, AI and messaging provider before deployment or redistribution.

