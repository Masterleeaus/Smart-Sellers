# Titan Nova integration boundaries

- Laravel admin and venture control live in `app/extensions/TitanNova`.
- The Chatbot Titan app shell supplies the operator PWA through `titan-nova-operator.json`.
- WorkCore remains authoritative for customers, leads, jobs, bookings, schedules, workforce, invoices and payments.
- Titan Nova owns opportunities, ventures, genomes, missions, experiments, simulations, decision gates and portfolio learning.
- Flutter customer-hub changes are deferred until the detected app inventory is reviewed; no guessed mobile path is created.
- All operational writes must use WorkCore governed actions and carry tenant, actor, causation, correlation and idempotency context.
