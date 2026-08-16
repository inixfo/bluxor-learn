# Project 21 — Sales Pipeline Setup Checklist

- [ ] Confirm the HubSpot pipeline used by this workflow.
- [ ] Copy every stage INTERNAL ID, not just the display label.
- [ ] Replace every REPLACE_STAGE_ID_* placeholder in the Stage Map Code node.
- [ ] Configure HubSpot Developer API credentials for the Trigger.
- [ ] Configure HubSpot Service Key/App Token (or OAuth2) for Get Deal and Task nodes.
- [ ] Set the HubSpot Trigger event to Deal Property Changed → dealstage.
- [ ] Confirm only one n8n HubSpot Trigger uses the selected HubSpot app target URL.
- [ ] Set YOUR_CHAT_ID and Telegram credential.
- [ ] Test duplicate webhook delivery.
- [ ] Test a stale/out-of-order stage event.
- [ ] Test Closed Won and Closed Lost separately.
- [ ] Verify task due dates and timezone assumptions.
- [ ] Decide owner fallback behavior for unowned deals.
- [ ] Activate only after test deals pass the matrix.
