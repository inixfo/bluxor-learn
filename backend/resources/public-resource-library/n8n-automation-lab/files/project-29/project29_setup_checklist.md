# Project 29 — Setup Checklist

1. Import TOOL-01, TOOL-02 and TOOL-03 first.
2. Create the three Data Tables from the supplied CSV schemas.
3. Replace Data Table ID placeholders.
4. Connect Gmail credential in TOOL-03.
5. Import the main AI Agent workflow.
6. Select the imported child workflows in each Call n8n Sub-Workflow Tool node.
7. Connect an OpenAI Chat Model credential/model available in your n8n instance.
8. Connect the same memory sub-node to Chat Trigger and AI Agent where supported by your version.
9. In the AI Agent Tools panel, configure HUMAN REVIEW for TOOL-03 Send Customer Email.
10. Keep TOOL-03 disabled from production use until approval behavior is tested.
11. Run test cases including denial, prompt injection, duplicate task and tool failure.
12. Attach Project 27 Error Workflow / incident routing where appropriate.
13. Keep Chat Trigger private while testing; authenticate/authorize any production-facing chat surface.
