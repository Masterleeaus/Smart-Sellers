# Titan Chatbot Vertical Templates — Pass 1

## Purpose

Wire the existing canonical Titan vertical catalogue into the shared Chatbot PWA shell without creating separate PWAs or duplicate WorkCore business logic.

## Architecture

- Chatbot remains the shared local-first shell.
- Titan Zero remains the orchestration and governance layer.
- WorkCore remains authoritative for structured business records and operational rules.
- Vertical templates are configuration overlays over the shared shell.
- WorkCore workspaces are reusable functional templates, not new domain authorities.

## Canonical verticals

1. Field and Home Services
2. Accommodation and Hospitality
3. Real Estate
4. Salons and Personal Care
5. Fitness and Membership
6. Automotive Services
7. E-commerce and Retail
8. Hire and Rental
9. Booking, Reservations and Capacity

Facilities maintenance remains a Field and Home Services subtype.

## WorkCore workspace templates

- CRM
- Jobs and Projects
- Crew and Team
- Finance

## Pass 1 changes

- Expose platform applications, business verticals and WorkCore workspaces as separate groups in the existing shell builder.
- Load all template schemas through `TemplateSchema::allTemplateSchemas()`.
- Apply each vertical's recommended WorkCore workspace composition by default.
- Allow a tenant to remove or re-enable individual workspace templates.
- Persist workspace composition through the existing `shell_builder_config` JSON field.
- Persist `titan_template` and shell configuration during initial chatbot creation as well as later customization.
- Keep the browser-side structured shell cache non-enumerable so the existing FormData serializer cannot submit `[object Object]`.
- Keep the source and published `titan-shell-builder.js` runtimes aligned.

## Offline boundary

Vertical templates may configure local records, packs, navigation, prompts and queued commands. They do not turn an offline client into an independent scheduling, finance, inventory, compliance or scarce-resource authority. WorkCore remains server-authoritative where the relevant domain requires confirmation.

## Next pass

- Make the Configure/Identity step vertical-first instead of showing a flat legacy template list.
- Add role presets per vertical.
- Compose commerce modes (booking, shop, hire, stay) into the same shell.
- Add vertical-aware offline pack selection and capability filtering.
- Add vertical-specific home widgets and quick actions using existing WorkCore read models and governed actions.
