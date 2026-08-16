# Project 24 — Setup Checklist

- [ ] Create the `feedback_state` n8n Data Table from the CSV schema.
- [ ] Import the Feedback Requester workflow and replace the Data Table ID.
- [ ] Import the Feedback Response Processor and replace the Data Table ID.
- [ ] Import the No-Response Reminder workflow and replace the Data Table ID.
- [ ] Connect Gmail OAuth2 credentials.
- [ ] Connect Telegram credentials and replace the team chat ID.
- [ ] Connect an AI chat-model credential for sentiment classification.
- [ ] Publish the response form workflow and copy its production Form URL.
- [ ] Replace `REPLACE_WITH_PROJECT24_FORM_URL` in requester/reminder templates.
- [ ] Keep URL query data opaque: use only `feedback_key`, not email/name/private data.
- [ ] Set review policy to `none` first; if using `all_respondents`, use the same neutral invitation for every eligible respondent.
- [ ] Test duplicate requests, duplicate responses, invalid keys, AI failure, low ratings, and Gmail failure.
- [ ] Publish only after the test matrix passes.
