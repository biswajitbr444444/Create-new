# PocketCat V18.6 — File Storage & Sharing Reliability

## Changelog

- **Upload queue** → Cancelled/failed entries could remain stuck in the queue → Added permanent per-item Remove/Dismiss, explicit retry/dismiss actions, and a confirmed “Clear all cancelled / failed” bulk action that leaves active and queued uploads untouched.
- **Upload completion** → Newly uploaded files required a separate manual sharing step → After the file passes scanning, the system attempts to create a secure tracked share link and adds Open/Copy link controls to the upload card. Files that do not pass scanning are not shared.
- **File metadata** → Users had no quick metadata view → Added an owner-authorized metadata API and Info action showing type, size, scan state, timestamps, folder-relative path, and download counters without returning backend storage locations.
- **Bulk deletion** → Files could only be deleted one at a time → Added owner-scoped bulk deletion with confirmation, storage cleanup, catalog cleanup, and count refresh.
- **Folder management** → There was no persistent folder-creation entry point on File Storage & Sharing → Added validated folder creation backed by the existing media catalog and a folder listing with metadata.
- **Expiry links** → Sharing expiration required manual date entry → Added presets for 1 hour, 6 hours, 24 hours, 2 days, 7 days, 1 month, and never expire, while retaining a custom date/time option.
- **Link analytics** → A file needed several steps to create a tracked link → Added “Create Link & Analytics” to file actions, using the existing secure share and short-link engine and opening Links & Growth at the newly created link.
- **Release management** → The OTA updater still reported V18.5 → Updated the deployed version marker and admin release-details page to V18.6.

## Verification

- PHP syntax check completed for all 183 PHP files in the package.
- Node syntax check passed for `assets/js/file-sharing-v14-upload.js`.
- Static checks confirmed the new queue cleanup, metadata, bulk-delete, folder-create, expiry-preset, post-upload share, and analytics-link paths are present.

## Deployment notes and scope

This update preserves the existing database and uses the current schema/catalog. Folder creation is a named folder entry in the media catalog; this release does not claim to implement a complete filesystem-style recursive folder copy/move engine, universal archive extraction for every optional system utility, or a full IDE-grade text editor. Those features require additional storage-provider-specific operations and runtime dependencies and must be implemented/tested separately before being described as production-complete. Automatic post-upload sharing requires the file scanner to mark the file clean; quarantined or still-pending files are not shared.
