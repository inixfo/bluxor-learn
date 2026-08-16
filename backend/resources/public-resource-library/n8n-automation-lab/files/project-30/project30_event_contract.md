# Project 30 — Canonical Business Event Contract

Required fields:
- event_id — globally stable idempotency identity for this event
- trace_id — correlation ID shared across related workflows
- event_type — namespaced event type such as lead.captured or deal.closed_won
- schema_version — start with business.event.v1
- source — emitting system
- entity_type / entity_id — stable business object identity
- occurred_at — time at source
- data — event-specific payload

Rules:
1. event_id must not change on retry/re-delivery.
2. trace_id may span multiple different events in one lifecycle.
3. Do not put credentials, passwords or raw secrets in data.
4. Add fields compatibly inside v1; use v2 for breaking meaning/type changes.
5. Domain workflows remain the source of truth for business state; the event ledger tracks orchestration state.
