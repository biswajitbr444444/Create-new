# PocketCat V18.4 — Production Media Expansion (incremental)

## Changelog
- **Media deletion** → direct deletion could immediately remove library metadata and media bytes → ordinary single/bulk deletion now soft-deletes catalog records into Trash and records actor/time.
- **Restore** → trashed media had no recovery flow → added single and bulk restore, ownership-scoped updates, and explicit name-conflict handling without overwriting active items.
- **Permanent deletion** → no separate irreversible action existed → added explicit permanent delete and Empty Trash controls; local media paths are resolved and checked against the user's media root before unlinking.
- **Retention** → trash could grow indefinitely → added scheduled cleanup to `cron/file_cleanup.php`; `media_trash_retention_days` accepts 7, 14, 30, 60, 90, or 0 (never), defaults to 30, and cleanup is capped per run.
- **Audit trail** → media delete/restore operations lacked a unified per-user record → added `media_audit_events` and events for trash, restore, permanent delete, retention cleanup, and collection creation.
- **Duplicate detection foundation** → media catalog had no content hash field → SHA-256 is persisted for new local media uploads and a user-scoped duplicate-group API is available.
- **Collections foundation** → media had no virtual album tables/API → added owner-scoped collection create/list/rename/delete and membership add/remove APIs; collection deletion does not delete underlying files.
- **Usage and recency endpoints** → no Media Library-specific usage/recent API existed → added scoped `/api/media.php?action=quota` (media-catalog usage summary only, not account-wide quota enforcement), `recent` (recently modified catalog entries), `duplicates`, `trash`, and `collections` reads.
- **API parity** → API bulk delete bypassed the page's lifecycle → API bulk deletion now moves assets to Trash; API restore validates original-folder name conflicts.
- **Database** → no Trash/hash/audit/collection schema → added `database/migration-v18.4-media-production-expansion.sql` and updated `database/schema.sql` for fresh installs.

## Migration / deployment
1. Back up the database.
2. On an existing V18.3 database, apply `database/migration-v18.4-media-production-expansion.sql` once.
3. On a fresh install, use the updated `database/schema.sql` instead of applying the ALTER migration afterward.
4. Ensure the existing scheduled CLI task invokes `cron/file_cleanup.php`; configure `media_trash_retention_days` if a non-default retention policy is desired.

## Current limitations / not claimed complete
This incremental release does **not** implement the entire requested production expansion. Version-history UI and retention policies, transactional quota reservations/enforcement, comprehensive advanced search filters/pagination, duplicate resolution UI, background integrity scans, full share management/revocation, session/device management, full permissions foundation, command palette, global drag-and-drop moves, full activity UI, orphan scanners, and the complete security/race/accessibility test matrix remain outstanding. Existing upload/streaming, archive, selection, share, and viewer implementations were preserved rather than replaced.
