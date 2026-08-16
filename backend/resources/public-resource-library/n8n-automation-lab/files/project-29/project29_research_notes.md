# Project 29 — Primary Research Notes
Checked: 13 Aug 2026

Primary sources:
- AI Agent: https://docs.n8n.io/integrations/builtin/cluster-nodes/root-nodes/n8n-nodes-langchain.agent/
- Tools Agent: https://docs.n8n.io/integrations/builtin/cluster-nodes/root-nodes/n8n-nodes-langchain.agent/tools-agent/
- Call n8n Workflow Tool: https://docs.n8n.io/integrations/builtin/cluster-nodes/sub-nodes/n8n-nodes-langchain.toolworkflow/
- How tools work: https://docs.n8n.io/build/integrate-ai/understand-ai-components/how-tools-work/
- Human-in-the-loop for tools: https://docs.n8n.io/build/integrate-ai/ai-examples/human-in-the-loop-for-tools/
- Chat Trigger: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-langchain.chattrigger/
- Simple Memory: https://docs.n8n.io/integrations/builtin/cluster-nodes/sub-nodes/n8n-nodes-langchain.memorybufferwindow/
- How memory works: https://docs.n8n.io/build/integrate-ai/understand-ai-components/how-memory-works/
- Use AI for parameters ($fromAI): https://docs.n8n.io/build/integrate-ai/ai-examples/use-ai-for-parameters/
- AI Agent source (current defaultVersion observed 3.1): https://github.com/n8n-io/n8n/blob/master/packages/%40n8n/nodes-langchain/nodes/agents/Agent/Agent.node.ts
- ToolWorkflow source (current defaultVersion observed 2.2): https://github.com/n8n-io/n8n/blob/master/packages/%40n8n/nodes-langchain/nodes/tools/ToolWorkflow/ToolWorkflow.node.ts

Current details checked:
- AI Agent connects to a chat model and one or more tools; the agent decides which tools to call.
- The current AI Agent source lists defaultVersion 3.1.
- The current Call n8n Sub-Workflow Tool source lists defaultVersion 2.2.
- n8n supports human review before selected AI tool calls; the workflow pauses until approval/denial.
- Chat Trigger + Agent can share memory; current Chat Trigger docs recommend the same memory source for a single source of truth.
- $fromAI() can populate tool parameters dynamically, but child workflows should still validate all arguments.

Version note: verify node UI and available model names in the reader's installed n8n version before production use.
