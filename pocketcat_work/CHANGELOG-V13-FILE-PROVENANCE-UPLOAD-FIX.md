# PocketCat V13 — File Provenance, Role Navigation & Upload Reliability

- Admins no longer see the user Media Library in navigation and `/media/` rejects admin access.
- Added upload provenance categories: Media Assets, Shared Files, Support Attachments, AI Workspace.
- File Storage, Support Chat, and AI attachments are registered in the user's Media Library.
- Added category tabs to `/media/`.
- Hardened media folder/drop upload handling and reload behavior after successful uploads.
- Added multipart form encoding to user/admin support composers.
- AI attachment-only messages are now allowed by removing the accidental textarea `required` constraint.
- Added authenticated `/api/chat-attachment.php` to eliminate direct uploads-directory access/403 issues.
- Removed physical upload paths from AI prompt context.
