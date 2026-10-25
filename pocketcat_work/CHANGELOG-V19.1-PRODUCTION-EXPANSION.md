# PocketCat V19.1 — File Storage Production Expansion

## Implemented in this incremental release

- **File deletion → destructive unlinking happened immediately →** normal File Storage delete now marks an owned file as trashed, preserves the physical object and metadata, hides it from active listings, and records an audit event.
- **File recovery → trashed storage files had no recovery screen →** added a responsive Trash page with owner-scoped restore and permanent-delete actions. Restore preserves the original relative path/name and does not overwrite unrelated files.
- **Trash expiry → trashed canonical files could remain indefinitely →** added configurable `file_trash_retention_days` cleanup in the existing CLI cleanup job (allowed values: 7, 14, 30, 60, 90, or 0 for never; default 30). Expired trash revokes dependent share links and becomes eligible for the existing retryable physical cleanup.
- **Share revocation → share links had no revoked state and public endpoints did not reject revocation →** added `revoked_at`, owner-scoped share management, immediate server-side revocation checks on share pages, share assets, and share-based downloads, plus link regeneration.
- **Share visibility → no unified share overview →** added Share Management with active/expired/revoked filtering, access counts, last recorded access, copy/open, analytics, revoke, and regenerate actions.
- **Search → media catalog search lacked combinable structured filters/pagination →** extended the existing Media API with paginated advanced search for text, type, size range, dates, folder, favorite and active-shared status; added paginated favorites, collection-item listing, and activity feeds.
- **Audit → file lifecycle events were not centralized across the storage area →** added `storage_audit_events` for trash, restore, permanent delete, share revoke/regenerate and retention events. Entries avoid raw tokens and storage paths.
- **Quota concurrency → concurrent upload creation relied on a simple used-bytes check →** retained the existing per-user upload lock and pending file records as the quota reservation mechanism; uploads fail closed if the per-user lock cannot be acquired.
- **Storage path privacy → diagnostic responses included absolute local paths →** removed storage and temporary-directory paths from user-facing file API diagnostics/errors.
- **Compatibility → existing file/share/media systems risked parallel implementations →** extended `file_objects`, `file_versions`, `media_assets`, `file_share_links`, existing cleanup and existing API conventions rather than adding a second storage or media engine.

## Database changes

- Added `database/migration-v19.1-production-expansion.sql` for existing V19.0 databases.
- Updated `database/schema.sql` for fresh installations.
- Added `file_objects.trashed_at` / `trashed_by`, `file_share_links.revoked_at`, search/trash indexes, and `storage_audit_events`.

## Deployment

1. Back up the database and application files.
2. Apply `database/migration-v19.1-production-expansion.sql` once to an existing V19.0 database. Fresh installs should use the updated `database/schema.sql` and should not apply the ALTER migration afterward.
3. Ensure the existing scheduled CLI task runs `cron/file_cleanup.php`; this job now handles file-trash retention and cleanup.
4. Configure `file_trash_retention_days` to `7`, `14`, `30`, `60`, `90`, or `0` (`0` means never). The default is 30 days.

## Explicit limitations

This is an incremental production expansion, not a claim that every item in the full V19.1 wish list is complete. The current V19.0 architecture's `file_versions` rows can point at the same storage key as the active file, so safe historical-content restoration cannot be claimed until version snapshots are stored immutably. Full quota reservation ledger integration, comprehensive activity UI, version restore UI, integrity scanning, orphan scanners, device/session management, permissions/2FA, command palette, global file/folder drag-move, and the complete security/race/accessibility matrix remain out of scope for this increment. Existing core upload, archive, viewer, selection, and media collection systems were not replaced.
