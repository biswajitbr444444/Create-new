# PocketCat V11.37 — File Upload & Sharing Hardening

## Patch purpose
This patch fixes the multi-file/chunk upload failure path, preserves folder-relative paths, hardens S3 multipart signing, and adds owner-controlled public file sharing primitives.

## Files replaced / added
- `files/index.php` — resilient resumable local upload UI, folder picker, selected-file ZIP, share-link modal, email sharing, user-scoped delete.
- `api/files.php` — JSON-safe upload API, fatal/exception handling, diagnostics, local chunk locking, S3 multipart completion, ZIP generation, share-link creation and email sharing.
- `includes/files.php` — safe relative paths, storage keys, environment diagnostics and share helpers.
- `includes/functions.php` — idempotent schema bootstrap for relative paths, view-only flags, versions and share bundles.
- `includes/policy.php` — file feature flags for folder uploads, ZIP, public sharing, email sharing and view-only links.
- `file/index.php` — public preview/share page honors view-only links.
- `file/download/index.php` — enforces view-only and max-download limits atomically.
- `file/share/index.php` — public batch/folder-style share landing page with image/PDF/audio/video previews and ZIP action.
- `.htaccess` — `/file/share/<token>` route.
- `database/migration-v11.37-file-sharing-upload-hardening.sql` — database migration.
- `README-V11.37-FILE-UPLOAD-SHARING-HARDENING.md` — this deployment note.

## Deployment
1. Extract this ZIP over the existing PocketCat application.
2. Ensure the web/PHP process can write `uploads/files/` and its temporary `.chunks/` directory.
3. Confirm `upload_tmp_dir` is writable. The upload API exposes `/api/files.php?action=diagnostics` for authenticated diagnostics.
4. Apply `database/migration-v11.37-file-sharing-upload-hardening.sql` to an existing database, or allow PocketCat's bootstrap schema migration to add the columns/tables.
5. If S3/R2 is configured, verify the endpoint, bucket, region and credentials. Multipart part URLs now sign `partNumber` and `uploadId` as part of the canonical query.
6. Configure SMTP if email sharing is required.
7. Configure the admin policy matrix at `/admin/control-plane/` to enable folder uploads, ZIP downloads, public shares and email sharing for each tier.

## Upload 500 diagnostic path
The browser now receives structured JSON with a request reference instead of a generic unhandled 500. Common causes surfaced by the API are PHP `upload_max_filesize`, `post_max_size`, `upload_tmp_dir`, unwritable storage directories, incomplete chunks, quota violations and storage-provider failures.

The local fallback deliberately uses small chunks below the detected PHP request ceiling. PHP's server-level limits cannot be raised safely from application code on all hosts; configure them in PHP/FPM, `.user.ini`, or the hosting control panel when available.

## Security / tenancy
All user file API reads and mutations include the authenticated `user_id`. Batch share creation only accepts clean files belonging to the current user. Public share pages expose only the files attached to their opaque share token. Passwords are stored as password hashes.
