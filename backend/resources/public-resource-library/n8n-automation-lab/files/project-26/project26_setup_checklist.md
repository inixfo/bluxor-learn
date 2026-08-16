# Project 26 — Setup Checklist

- [ ] Set the workflow timezone to the business timezone.
- [ ] Create `business_report_history` from the supplied schema.
- [ ] Create `sales_daily_snapshot` if you want Level 4 sales metrics in the report.
- [ ] Replace all Data Table ID placeholders for Project 22–25 state tables.
- [ ] Populate the sales adapter table or leave sales coverage explicitly unavailable.
- [ ] Connect OpenAI Chat Model credentials (optional; deterministic fallback still works).
- [ ] Connect Gmail and set the report recipient.
- [ ] Connect Telegram and set the internal chat ID.
- [ ] Confirm one reporting currency or add a currency-conversion policy before summing money.
- [ ] Test a zero-activity day.
- [ ] Test one missing source and confirm status becomes `degraded`.
- [ ] Replay the same business date and verify the report is not delivered twice.
- [ ] Test timezone boundaries around midnight.
- [ ] Publish only after the full test matrix passes.
