# AI Agent Safety Policy

## Tool classes
- READ: may run without approval when access is scoped.
- REVERSIBLE INTERNAL WRITE: may run without approval only if validation + idempotency + audit are in place.
- EXTERNAL / SENSITIVE SIDE EFFECT: require human review before execution.
- DESTRUCTIVE / SECRET-EXPOSING: do not expose to the agent.

## Project 29 policy
- TOOL-01 Customer Lookup: READ.
- TOOL-02 Create Follow-up Task: REVERSIBLE INTERNAL WRITE.
- TOOL-03 Send Customer Email: EXTERNAL SIDE EFFECT → HUMAN REVIEW REQUIRED.

The model cannot approve its own action. Approval must come from the configured n8n human-review channel.
