# Project 30 — Primary Research Notes
Checked: 13 Aug 2026

Primary sources:
- Break workflows into smaller parts: https://docs.n8n.io/build/flow-logic/break-workflows-into-smaller-parts
- Execute Sub-workflow: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.executeworkflow
- Data Table: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.datatable
- Handle errors gracefully: https://docs.n8n.io/build/flow-logic/handle-errors-gracefully
- Error Trigger: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.errortrigger
- Human-in-the-loop for tools: https://docs.n8n.io/build/integrate-ai/ai-examples/human-in-the-loop-for-tools
- Source control and environments: https://docs.n8n.io/administer/use-source-control-and-environments
- Work with environments: https://docs.n8n.io/administer/use-source-control-and-environments/work-with-environments
- Projects/RBAC: https://docs.n8n.io/administer/manage-users-and-access/set-permissions-and-roles-rbac/organize-work-in-projects
- Queue mode: https://docs.n8n.io/deploy/host-n8n/configure-n8n/scaling/enable-queue-mode
- Control concurrency: https://docs.n8n.io/deploy/host-n8n/configure-n8n/scaling/control-concurrency
- Manage execution data: https://docs.n8n.io/deploy/host-n8n/configure-n8n/scaling/manage-execution-data
- Insights: https://docs.n8n.io/administer/observe-and-log/track-usage-with-insights
- Log streaming: https://docs.n8n.io/administer/observe-and-log/stream-logs-to-external-systems
- Workflow executions: https://docs.n8n.io/build/understand-workflows/understand-executions
- n8n releases: https://github.com/n8n-io/n8n/releases

Current details checked:
- n8n recommends sub-workflows for breaking larger workflows into smaller reusable parts.
- Data Table row operations support get/insert/update/upsert/delete/filter patterns.
- Error workflows run when linked workflow executions fail.
- n8n supports human review before selected AI tool calls.
- Git-based source control/environments exist, but credential and variable values are not synced via Git and must be populated per environment.
- Queue mode is n8n's recommended scaling approach for best scalability; workers process queued executions.
- n8n projects group workflows and credentials and use project roles for access control.
- Insights/log streaming and some governance features depend on edition/plan.
- Latest stable release observed on 13 Aug 2026: n8n 2.34.5 (released 12 Aug 2026); 2.35.1 is marked pre-release.

Version/edition note: verify the reader's installed n8n version and plan before relying on enterprise/governance/scaling UI or features.
