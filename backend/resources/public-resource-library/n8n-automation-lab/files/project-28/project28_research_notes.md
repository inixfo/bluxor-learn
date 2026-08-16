# Project 28 — Primary Research Notes
Checked: 13 Aug 2026

Primary sources used:
- Execute Sub-workflow Trigger: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.executeworkflowtrigger/
- Execute Sub-workflow: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.executeworkflow/
- Break workflows into smaller parts: https://docs.n8n.io/build/flow-logic/break-workflows-into-smaller-parts/
- Convert to sub-workflows: https://docs.n8n.io/build/flow-logic/convert-to-sub-workflows/
- Workflow settings / caller restrictions: https://docs.n8n.io/build/manage-workflows/configure-workflow-settings/
- Workflow history: https://docs.n8n.io/build/manage-workflows/view-change-history/
- Export/import: https://docs.n8n.io/build/manage-workflows/export-and-import/
- n8n Packages: https://docs.n8n.io/build/manage-workflows/n8n-packages/
- Package export: https://docs.n8n.io/build/manage-workflows/n8n-packages/export-a-package/
- Package import behavior: https://docs.n8n.io/build/manage-workflows/n8n-packages/how-import-works/
- Data Table row operations: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.datatable/rows/
- Release list: https://github.com/n8n-io/n8n/releases

Observed stable release at research time: n8n 2.34.5 (2026-08-12). 2.35.1 was listed as pre-release.

Important current details checked:
- Execute Sub-workflow Trigger can explicitly define input fields/types, define inputs from a JSON example, or accept all data.
- Execute Sub-workflow can run once per item or once with all items.
- Execute Sub-workflow has a Wait for Sub-Workflow Completion option.
- Workflow settings can restrict which workflows are allowed to call a sub-workflow.
- n8n supports converting selected nodes into a sub-workflow.
- Current n8n Packages tooling can export/import workflow bundles and account for statically referenced sub-workflow dependencies; verify availability/behavior on the reader's installed version.
