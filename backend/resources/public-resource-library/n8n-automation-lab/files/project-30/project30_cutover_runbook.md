# Project 30 — Cutover & Rollback Runbook

## Before cutover
- Export/backup current production workflows.
- Record workflow versions and Data Table schemas.
- Verify production credentials and webhook URLs.
- Pause duplicate legacy automations that would create the same side effect.

## Cutover
1. Enable Gateway for a limited source/tenant.
2. Observe event ledger accepted/completed ratio.
3. Validate downstream business effects manually.
4. Expand traffic in stages.

## Rollback triggers
- duplicate external side effects
- material routing errors
- sustained failure/timeout rate
- cross-tenant/security issue

## Rollback
1. Disable/pause new ingress.
2. Restore previous published workflow/version as appropriate.
3. Do not blindly replay failed events. Classify each event and verify idempotency/side-effect state first.
4. Reconcile business systems before resuming traffic.
