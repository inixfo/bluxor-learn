# Project 22 — Setup Checklist

- Create an n8n Data Table named `client_onboarding`.
- Add the columns from `project22_onboarding_table_schema.csv`.
- Import the Project 22 workflow.
- Replace the Data Table ID placeholder.
- Connect Google Drive OAuth2 credentials.
- Create a private onboarding root folder and replace `REPLACE_WITH_ONBOARDING_ROOT_FOLDER_ID`.
- Connect Gmail OAuth2 credentials.
- Connect Telegram credentials and replace the team chat ID.
- Import the demo caller or connect Project 21's CLOSED_WON branch with Execute Sub-workflow.
- Select the imported Project 22 workflow inside the parent Execute Sub-workflow node.
- Test with a fake client and a sandbox folder first.
- Verify that running the same handoff twice does not create two folders or send two welcome emails.
- Review Google Drive permissions before using real client data.
- Publish the sub-workflow only after the test matrix passes.
