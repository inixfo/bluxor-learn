# Project 28 — Setup Checklist

1. Import the three child workflows first: Normalize Contact, Idempotency Gate, Team Alert.
2. In each Execute Sub-workflow Trigger, review the declared workflow inputs and expected types.
3. Configure **which workflows can call this workflow** using the narrowest practical caller policy.
4. Create the `p28_idempotency_keys` Data Table from the supplied CSV and replace its ID in UTIL-02.
5. Connect the Telegram credential and replace the team chat ID in UTIL-03.
6. Import the Demo Parent Caller and replace each child workflow placeholder with the imported workflow selected from the list.
7. Keep **Wait for Sub-Workflow Completion** enabled when the parent needs the child output or failure signal.
8. Run the Contract Test Harness with valid, invalid and duplicate cases.
9. Publish child workflows before publishing parents that depend on them.
10. Before changing a shared output field, search all callers and use the versioning checklist.
11. Export JSON backups. Where your current n8n supports Packages, test a package export/import in a non-production project before relying on it for promotion.
