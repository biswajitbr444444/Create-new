# PocketCat v19.72 — Platform Activity & Auto-Fill Live Sync

## Changes
- Added the **Show Live Workspace Section on Homepage** master setting to `/admin/platform-activity/`.
- Made the five public platform counters read the saved activity settings, with database-count fallbacks only when no override exists.
- Added the five shared platform counters to the user dashboard.
- Added a no-cache stats API response and browser polling so the homepage and dashboard reflect saved counters and auto-fill changes without a hard refresh.
- Persisted the homepage section toggle; the homepage section can be hidden or shown immediately for open pages and on subsequent visits.
- Synchronized Latest Month with the configured Registered Users value when linking is enabled, including the final point of the growth chart.
- Improved auto-fill catch-up across elapsed intervals, prevented invalid zero/under-one random growth increments, and reset interval clocks when settings are saved.
- Applied maximum limits to manually saved current values and kept the Site Editor counter save path aligned with auto-fill timing.
- Updated asset cache versions for the activity sync scripts and styles.

## Validation
- `make check` passed the existing policy, quota, plan-catalog and webhook smoke tests and PHP syntax checks.
- JavaScript syntax checks passed for the platform-stats and growth-chart scripts.
- Production database and browser testing were not available in this environment.
