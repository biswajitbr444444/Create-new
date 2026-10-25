# PocketCat V17.3 — Admin Version Details Route Fix & Current Release Notes

## Release scope
V17.3 is a focused maintenance release for the administrator Version Details feature introduced in V17.2. It fixes the production 404 caused by the missing pretty-URL rewrite, integrates Version Details into the admin sidebar, updates release metadata to V17.3, and changes the Version Details page to show only the active V17.3 release notes.

## All V17.3 changes (V17.2 → V17.3)

### 1. Version Details route / 404
- **Problem:** The admin UI linked to `/admin/version-details/`, but `.htaccess` had no explicit rewrite for that path. The server therefore fell through to its normal document handling and returned the hosting provider's 404 page.
- **Fix:** Added the exact case-sensitive rewrite `^admin/version-details/?$` → `admin/version-details.php`.
- **Compatibility:** Both `/admin/version-details/` and `/admin/version-details` resolve to the same protected PHP controller. No existing admin routes were changed.

### 2. Admin sidebar integration
- **Problem:** Version Details was available from the dashboard card/top navigation but was missing from the main Administration sidebar list containing Posts, Pages, Categories, Tags, Comments and Users.
- **Fix:** Added **Version Details** to the admin sidebar under the CONTENT/admin navigation section.
- **Security:** The link remains inside the existing admin-view conditional, so normal users do not receive the admin navigation item.

### 3. Current-version-only details
- **Problem:** V17.2 displayed an expandable archive of every packaged changelog, which did not match the requirement to show only the active release's changes.
- **Fix:** Version Details now loads only `CHANGELOG-V17.3-VERSION-DETAILS.md` and renders the V17.3 release notes. Historical `CHANGELOG-V*.md` files remain untouched on disk for deployment/history but are not displayed on the current Version Details page.
- **Displayed scope:** V17.3 feature additions, bug fixes, route/code changes, security behavior, compatibility notes and QA results.

### 4. Active version metadata
- **Problem:** Application version remained V17.2.
- **Fix:** Updated `VERSION` from `17.2` to `17.3`.
- **UI:** Admin dashboard and top admin navigation now reference V17.3.

### 5. Admin dashboard Version Details card
- **Problem:** Dashboard copy referred to V17.2 changes and the old multi-release-history behavior.
- **Fix:** Updated the card description to identify V17.3 code updates, bug fixes and feature additions.

### 6. Browser asset cache identifiers
- **Problem:** Shared CSS and file-sharing/upload assets still used V17.2 cache identifiers after the release bump.
- **Fix:** Bumped applicable asset query-string versions to `17.3` in the shared application shell and file-sharing page so browsers receive the V17.3 assets without changing asset filenames.

## Files changed in V17.3
- `.htaccess` — added the `/admin/version-details/` rewrite rule.
- `VERSION` — changed active release from `17.2` to `17.3`.
- `admin/version-details.php` — changed the page from a multi-release archive to current-release-only V17.3 details.
- `includes/sidebar.php` — added the Version Details admin-sidebar item.
- `includes/navbar.php` — updated the admin top-navigation version label to V17.3.
- `admin/index.php` — updated Version Details dashboard copy for V17.3.
- `includes/functions.php` — bumped shared CSS cache identifiers to V17.3.
- `files/index.php` — bumped file-sharing/upload CSS/JS cache identifiers to V17.3.
- `README.md` — updated release documentation for V17.3.
- `CHANGELOG-V17.3-VERSION-DETAILS.md` — added the current release change record displayed by the admin page.

## QA / compatibility
- Pretty URL mapping is explicit and case-sensitive-safe for the deployed lowercase route.
- Version Details remains protected by the existing `require_admin()` check.
- Sidebar visibility remains protected by the existing admin-role + Admin View condition.
- Current-release page no longer reads or renders historical changelog content.
- Existing changelog files are not deleted or modified.
- No database migrations, schema changes, destructive operations, or data rewrites were introduced.
- Existing upload, media, file-sharing, analytics, authentication and CMS behavior is preserved.
