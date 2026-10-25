# PocketCat V17.0 — Upload Reliability & Admin Mobile QA

## Scope
Production hardening based on the V16.9 screenshots covering `/files/` upload failures and `/admin/files/` mobile moderation layout.

## Changes
- **File upload initialization → HTTP 500 / “Upload service failed”** → The `file_objects` INSERT supplied fewer values than its declared columns, causing every normal file-upload session to fail before the first chunk → Corrected the SQL placeholder count so file records initialize successfully.
- **File upload cache → stale client bundle risk** → `/files/` loaded its upload CSS/JS with legacy `14.1` cache identifiers → Bumped both file-page asset cache keys to `17.0`.
- **Global asset cache → stale shared CSS risk** → Shared application/responsive CSS still referenced V16.9 → Bumped both cache keys to `17.0`.
- **Admin moderation tables → mobile overflow at tablet/phone widths** → The base responsive stylesheet applies a 760px table minimum below 800px, while the V16.9 override only activated below 700px → Moved the admin moderation card transformation to the 800px breakpoint so all narrow layouts receive stacked rows and visible actions.
- **Version metadata → stale release number** → `VERSION` still contained `16.1` → Updated release metadata to `17.0`.

## QA
- PHP syntax validation: PASS for all PHP files.
- JavaScript syntax validation: PASS for all asset JavaScript files.
- ZIP integrity: PASS after packaging.
- No database schema/migration changes added.
- Existing storage, sharing, analytics, and database relationships preserved.

## Hosting note
The InfinityFree dashboard/control-panel screenshots are third-party hosting UI and are not part of the PocketCat codebase. V17.0 fixes the PocketCat `/files/` and `/admin/files/` code paths visible in the supplied screenshots.
