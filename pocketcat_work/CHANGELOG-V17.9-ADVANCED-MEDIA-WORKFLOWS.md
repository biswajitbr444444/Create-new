# PocketCat V17.9 — Advanced Media Workflows

## Implemented in this package

- Media Library → Added secure expiring multi-item share creation for selected canonical File Storage assets, with 1 hour, 6 hours, 24 hours, 2 days, 7 days, 1 month, and never-expire presets.
- Media Library → Added per-item and bulk “Create Link & Analytics” actions that create a tracked short link pointing to the opaque-token share landing page and navigate to Links & Growth.
- Media Library → Added Info actions for media name, folder, type, MIME, size, dimensions, and timestamps.
- Media Library → Added bulk copy and enhanced move conflict handling with Cancel, Skip Duplicates, Replace, and Rename Automatically choices.
- Media Library → Protected shared canonical file objects from being deleted when another media entry still references them.
- Upload queue → Added dismiss/remove actions for queued, active, failed, cancelled, and completed entries, plus “Clear all cancelled / failed”.
- Upload pipeline → Registered chunked Media Library uploads in the canonical File Storage catalog so they can use secure file-share workflows consistently.
- Share expiration → Applied the selected expiry to both the share token and associated short link; the public share route already rejects expired tokens.
- UI → Added responsive styles for the duplicate-handling dialog and bulk controls.

## Important limitations

This release does not yet implement every broad request in the original V17.9 specification. In particular, the full general-purpose archive engine (7z/LZ4/Zstandard/XZ and encrypted multipart archives), full virtual extraction workspace, full text-editor/search-and-replace suite, and mixed folder-tree share explorer are not implemented by this patch. The secure share endpoint currently supports selected media entries that have canonical File Storage records; remote URL-only assets are rejected rather than shared unsafely.

## Verification

- PHP syntax validation was run across the complete PHP source tree after changes.
- JavaScript syntax validation was run across the existing JavaScript source tree.
- ZIP archive integrity was checked after packaging.
- Live database integration, authenticated browser flows, upload concurrency, storage-driver behavior, and production server configuration still require deployment-environment QA.
- Public share page → Removed embedded file IDs and file public tokens from per-item preview/download controls; previews and downloads now use an opaque HMAC item key scoped to the share token.
- Public share streaming → Added a share-aware streaming endpoint with expiration/password/membership checks, local-storage root containment, safe content headers, range requests for media, and text-preview size limits.
- Upload completion → Automatically creates and copies a 7-day secure share link for newly uploaded Media Library items when the resumable upload queue completes successfully.
