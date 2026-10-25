# PocketCat V11.7 — InfinityFree Installer HTTP 500 Fix

## Root cause
V11.6 called `menu_schema()` from `bootstrap.php` before first-run installation had created the runtime database configuration. `menu_schema()` immediately called `db()`, which correctly refused to connect without configuration, but the exception escaped and produced HTTP 500 at `/install/`.

## Fix
- `bootstrap.php` initializes the navigation schema only after installation is locked.
- `menu_schema()` is independently guarded by installation/config checks and a safe exception boundary.
- `/install/index.php` safely defaults an absent `REQUEST_METHOD` to `GET`.
- No database credentials or passwords were changed.

## Current hosting defaults
- Domain: `pocketcat.online`
- Document root: `pocketcat.online/htdocs`
- MySQL host: `sql104.infinityfree.com`
- Database: `if0_42811059_pocketcat_online`
- MySQL user: `if0_42811059`
- Port: `3306`
- Website IP: `185.27.134.215`

## Deployment
Replace/merge the V11.6 files with this release, then open `/install/` again. The installer should render its first database step instead of HTTP 500.
