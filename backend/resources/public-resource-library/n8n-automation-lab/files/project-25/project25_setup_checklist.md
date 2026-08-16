# Project 25 — Setup Checklist

- [ ] Create an n8n Data Table named `order_state` from the supplied CSV schema.
- [ ] Create/connect Shopify Access Token credentials including the app secret required by Shopify Trigger.
- [ ] Import the Paid Order Processor and set Trigger On = Order Paid if the import UI asks you to reselect it.
- [ ] Replace the Data Table ID placeholder in all workflows.
- [ ] Connect Gmail and Telegram credentials.
- [ ] Review `project25_product_routing_rules.csv` and replace demo SKU prefixes with your real catalog rules.
- [ ] Import the Shipment Update Listener, Cancelled Order Listener and Reconciliation Workflow.
- [ ] Replace team chat ID and customer-facing sender settings.
- [ ] Test physical, digital, mixed, duplicate, cancelled, missing-SKU and reconciliation cases.
- [ ] Confirm a duplicate paid webhook does not create duplicate warehouse tasks, access grants or emails.
- [ ] Do not mark an order fulfilled until the real fulfillment action succeeds.
- [ ] Publish only after test orders pass.

Research checked: 13 Aug 2026. n8n stable: 2.34.5. Shopify webhook reference: 2026-07 latest.
