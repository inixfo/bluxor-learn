# Project 30 — Go-Live Checklist

1. Freeze canonical event v1 fields and module registry.
2. Create production event ledger Data Table.
3. Import Dispatcher before Gateway and replace all workflow-ID placeholders.
4. Build/adapt P17/P24/P25 entry adapters to accept canonical events.
5. Import Closed Won Lifecycle and map Project 22 + Project 23 child workflow IDs.
6. Keep billing_approved=false by default; test that Closed Won alone does not issue an invoice.
7. Attach Project 27 Error Workflow to production-critical workflows.
8. Wire Project 26 Daily Business Report to production state tables.
9. Attach P30 read-only Business Status tool to Project 29 AI Agent if desired.
10. Configure least-privilege production credentials and project access.
11. Test duplicates, partial failure, replay, approval denial and stale events.
12. Test backups/history/rollback path before cutover.
13. Start with limited traffic and observe success rate + business exceptions.
14. Document owners for Sales, Client, Billing, Orders, Feedback and Platform incidents.
