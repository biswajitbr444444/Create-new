# PocketCat V16.9 — Admin File Moderation Mobile QA

## Scope
Hardening of `/admin/files/` based on the V16.8 production screenshot and regression review.

## Changes
- **File review table → mobile overflow/action access** → On phone widths the six-column table forced horizontal scrolling and could hide the Action controls → Added a responsive card-style table presentation below 700px, with visible field labels and full-width moderation buttons.
- **IP block/history tables → mobile readability** → Dense tables relied on horizontal scrolling → Added the same accessible responsive treatment for active blocks and audit history.
- **Admin file delete → local storage orphan risk** → Previous admin delete only changed the database status for local files, leaving the physical object on disk → Admin deletion now removes the local storage object before marking the record deleted.
- **Admin file delete → failed storage deletion** → Database state could be changed even when an S3/object deletion failed → Deletion now stops and reports an error when storage removal cannot be confirmed.
- **Admin file delete → unsupported provider ambiguity** → Non-local/non-S3 objects could be marked deleted without provider deletion → Unsupported storage drivers are now rejected instead of silently orphaning remote objects.
- **Media provenance cleanup → compatibility** → Cleanup referenced a canonical-file column that is not guaranteed by older schemas → Cleanup uses the established `Shared Files` + `source_ref_id` relationship.
- **File review list → deleted records** → Deleted records could reappear as moderation targets → Deleted objects are excluded from the review list.
- **Version/cache → V16.8** → Updated application asset cache references to **V16.9**.

## QA
- PHP syntax validation: PASS for all PHP files.
- JavaScript syntax validation: PASS for all asset JavaScript files.
- ZIP integrity: PASS.
- No database schema changes or destructive migrations added.
- Existing user-facing file deletion behavior remains unchanged; this release hardens the admin moderation path.
