# PocketCat V11.55 — AI Attachments Multi-Upload Fix

- Fixed AI Assistant **Attach files** resumable upload flow.
- Multiple files are processed independently, so one failed file does not cancel successful attachments.
- Each attachment is finalized and registered in `chat_attachments` with its conversation.
- Attachment IDs are sent to the AI chat endpoint and linked to the resulting user message after successful AI processing.
- Added safe same-origin credentials and robust JSON/error handling for init/chunk/finalize requests.
- Failed uploads are explicitly aborted to remove temporary upload data.
- Added upload progress state per selected file and clear failed state without hiding successful files.
- AI chat now supports sending attachment-only messages (no text required when files are attached).
- Conversation history returns attached files for user messages.
- Attachment links are shown in the conversation history.
- Existing resumable upload limits remain in place for shared-hosting compatibility.
