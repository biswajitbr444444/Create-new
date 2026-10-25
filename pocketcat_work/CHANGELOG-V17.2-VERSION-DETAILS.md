# PocketCat V17.2 — Admin Version Details & Release History

## Scope
V17.2 adds a dedicated administrator-facing Version Details page so administrators can see the active PocketCat release, the complete V17.2 change list, QA/compatibility notes, and the historical changelog archive without opening server files.

## Changes
- **Admin release visibility → version information was distributed across files** → Added `/admin/version-details/`, restricted to authenticated admin view, with the active version, release date, scope, and release status.
- **V17.2 change transparency → no single admin-facing change summary** → Added a complete V17.2 summary covering the Media Upload telemetry/retry work and the V17.2 admin version-details feature.
- **Historical release information → administrators had to inspect individual changelog files** → Added an expandable release-history archive that reads all packaged `CHANGELOG-V*.md` files in the application root and preserves their full text.
- **Release navigation → version details not reachable from the admin dashboard/top navigation** → Added Version Details links to the admin dashboard and admin top bar.
- **Version metadata → V17.1** → Updated `VERSION` to `17.2`.
- **Browser cache → V17.1 asset identifiers** → Bumped shared application, responsive, file-sharing and upload-page asset cache identifiers to `17.2` where applicable.
- **Security → changelog content could be treated as HTML** → Version history is escaped and rendered as text inside native `<details>` sections; changelog files cannot inject markup into the admin page.
- **Database compatibility → existing schema/data** → No database migrations, table changes, destructive operations, or data rewrites were introduced.

## Admin UI
- Current release card: `V17.2`.
- Release status and release scope.
- V17.2 detailed change list.
- QA and compatibility notes.
- Search/filter release history.
- Expandable full changelog text for every packaged `CHANGELOG-V*.md` release.
- Mobile-responsive layout suitable for the existing PocketCat admin interface.

## QA
- Admin route protected by `require_admin()` and therefore requires an authenticated administrator with Admin View enabled.
- Changelog file names are generated from a fixed `glob()` pattern in the application root; no user-supplied file path is accepted.
- Changelog contents are HTML-escaped before output.
- PHP syntax validation: PASS.
- JavaScript syntax validation: PASS.
- No database schema changes.
- Existing upload, media, file-sharing, analytics and authentication behavior preserved.
