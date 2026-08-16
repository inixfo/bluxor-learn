# Project 27 — Setup Checklist

- Create the `incident_state` Data Table from `project27_incident_state_schema.csv`.
- Import `project27_error_handler.json`.
- Replace the Data Table ID and Telegram/Gmail placeholders.
- Import `project27_recovery_worker.json`.
- Create an n8n API credential with the minimum execution retry scope needed.
- Replace the n8n API base URL / credential placeholder in the Recovery Worker.
- In each protected workflow, choose Project 27 as the **Error Workflow** in Workflow Settings.
- Add node-level Retry On Fail only to transient, retry-safe operations.
- Add durable business checkpoints before retrying side effects such as emails, provisioning, charges, file creation or order fulfillment.
- Test 503, timeout, 429, 401, 400, business validation, duplicate error events and exhausted retry budget.
- Confirm alerts contain no credentials or raw sensitive payloads.
- Publish only after the test matrix passes.
