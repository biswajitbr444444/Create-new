# PocketCat V15.6 — Consolidated Link Analytics + Admin Media Library Fix

## Purpose
V15.6 is the consolidated production build after V15.5. It keeps the V15.5 Admin View Media Library access fix and restores the V14.2 real-time Link Analytics behavior.

## Included fixes
- Admin View: `/media/` is accessible to administrators and Media Library remains scoped to the authenticated account.
- Admin View sidebar includes Media Library alongside File Storage & Sharing and Code Editor.
- Link clicks are written synchronously to `link_clicks`, so Total clicks and Live Click Stream do not depend on the cron worker.
- Local GeoLite2 data is captured with the click when available.
- Short-link redirects use `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` and `Pragma: no-cache`, preventing browser/proxy cached redirects from hiding new clicks.
- Existing queued/worker support for older pending events remains available.
- No destructive database changes are introduced by V15.6.

## Validation
- PHP syntax check: run `php -l` across all PHP files before deployment.
- JavaScript syntax check: run `node --check` across project JavaScript files.
- ZIP integrity: `unzip -t`.

## Deployment
Replace the current project with the complete V15.6 ZIP. No new database migration is required for this consolidated fix beyond the existing schema already used by V14/V15 builds.
