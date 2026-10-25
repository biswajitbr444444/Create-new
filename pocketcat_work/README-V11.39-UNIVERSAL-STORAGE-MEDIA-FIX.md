# PocketCat V11.39 — Universal Upload / Storage Deployment

This package is a drop-in update for the PocketCat application tree.

## Deployment
1. Back up the database and current PocketCat files.
2. Extract this ZIP over the existing PocketCat installation, preserving the directory structure.
3. Ensure `uploads/` and `storage/tmp/` are writable by the PHP process.
4. Open PocketCat once after deployment. The bootstrap schema guard creates missing V11.39 tables/columns automatically.
5. For controlled database deployment, run `database/migration-v11.39-universal-storage-media-fix.sql` after V11.38.

## Upload behavior
- Media Library: multiple files, dedicated folder picker, folder-aware drag/drop, and resumable chunk uploads.
- File Storage: multiple files, optional folder picker, resumable local chunks, direct cloud uploads where supported, and structured JSON errors.
- Support Chat and AI attachments continue to use the resumable upload engine.
- Application temporary assembly uses `storage/tmp`; PHP's own `upload_tmp_dir` remains a hosting-level setting and is reported by diagnostics.

## Storage
- Default system driver: **InfinityFree Local**.
- Cloud providers are opt-in and must be configured/connected before they become active.
- Existing File Storage records retain their own `storage_driver`, so switching the default does not rewrite old links.
- User storage preferences are stored in `user_storage_settings` and are used when new File Storage objects are created.
- Admin storage settings dynamically show only the fields relevant to the selected provider.

## Migration
Use `/admin/storage-migration/` for Local ↔ S3/R2 batch migration. The utility verifies the destination before changing the database record and only deletes a source object when the destination copy succeeds.

## Important shared-hosting note
No PHP application can override a hosting provider's hard `post_max_size` before PHP parses a request. The resumable uploader therefore keeps each application upload request small and uses `storage/tmp` for reassembly. If the host's PHP temporary upload directory itself is unavailable, the diagnostics response identifies it so the hosting configuration can be corrected.
