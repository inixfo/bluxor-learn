# Project 30 — Operations Runbook

For every incident capture: trace_id, event_id, event_type, entity_id, workflow/execution ID, last durable checkpoint, side effects already completed, owner.

Triage order:
1. Security/data isolation issue → stop affected ingress immediately.
2. Duplicate financial/customer side effect risk → pause route, inspect state.
3. Provider outage/429/5xx → apply Project 27 transient retry policy.
4. Validation/business data issue → human queue; do not retry unchanged payload.
5. Stuck accepted event → inspect dispatcher/module execution and checkpoint.

Recovery rule: never replay until you know what already happened.
