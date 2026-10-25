# PocketCat V13 — File Provenance & Upload Fixes

## Database
Run `database/migration-v13-file-provenance-categories.sql` once against the existing PocketCat database.

The migration adds provenance fields to:
- `media_assets.source_category/source_ref_id`
- `file_objects.source_type/source_category`
- `chat_attachments.storage_path/source_type/source_category`
- `support_messages.attachment_category`
- `upload_sessions.source_type/source_category`

New upload categories:
- Media Library → **Media Assets**
- File Storage & Sharing → **Shared Files**
- Support Chat → **Support Attachments**
- AI Chat / Agent → **AI Workspace**

File Storage and AI attachments are also registered in the authenticated user's Media Library so the source tabs can show them.

## Role access
`/media/` is regular-user only. Admins receive HTTP 403 and the Media Library navigation entry is removed from the admin sidebar/topbar/dashboard.

The existing `/admin/media/` CMS manager is kept as a separate admin content tool because the CMS editor depends on it; it is no longer exposed as the user Media Library navigation item.

## Uploads
All user-facing upload forms use `multipart/form-data` and CSRF validation. Media, support, and AI uploads use the resumable upload controller for shared-hosting-safe chunks.

## AI 403 fix
AI attachment links now use `/api/chat-attachment.php?id=...` instead of exposing the uploads directory directly. The endpoint authenticates the conversation owner and confines filesystem reads to that user's chat attachment directory. AI prompts also no longer include physical `/uploads/...` paths.
