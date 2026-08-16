# Project 23 — Setup Checklist

- Create an n8n Data Table named `invoice_state` and add columns from `project23_invoice_table_schema.csv`.
- Create a Google Docs invoice template using `project23_google_docs_invoice_template.md`.
- Replace `REPLACE_WITH_INVOICE_TEMPLATE_FILE_ID` in the issuer workflow.
- Connect Google Drive, Google Docs, and Gmail OAuth2 credentials.
- If you want copied invoices stored in a specific folder, configure the Drive Copy node for your account/drive model.
- Replace `REPLACE_WITH_INVOICE_STATE_TABLE_ID` in all Project 23 workflows.
- Import the demo caller and select the imported Project 23 issuer workflow.
- Confirm the workflow timezone before publishing the reminder workflow.
- Test with fake clients and a non-production Drive folder.
- Run the same invoice handoff twice and verify only one client-facing invoice email is sent.
- Test a failed email and verify a retry reuses the existing document.
- Test PAID state and confirm all reminders stop.
- For a real payment gateway, verify the provider webhook/signature before calling the payment-status child workflow.
- Review local invoice, tax, numbering, storage, and retention requirements before production use.
