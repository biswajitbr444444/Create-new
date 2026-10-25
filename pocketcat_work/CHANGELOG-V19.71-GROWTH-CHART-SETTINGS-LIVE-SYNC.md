# PocketCat v19.71 — Growth chart settings and live sync

## Changes
- Replaced hardcoded growth chart inputs with a shared database-backed configuration helper using `growth_base` and `growth_rates`.
- Added editable chart title, subtitle/description, and a Show/Hide DEMO DATA badge control to Admin → Live Site Editor → Global branding, logo, colors & labels.
- Added a read-only `/api/growth.php` endpoint that returns the same configuration and calculated six-month series used by server-rendered pages.
- Added lightweight client polling (5 seconds while visible) so open dashboard and public homepage charts refresh after settings are saved, without manual cache clearing or a hard refresh.
- Added the same dynamic chart to the public homepage.
- Disabled browser/proxy caching for app HTML responses and the growth API to avoid stale settings.

## Validation
- PHP syntax checks pass for the modified PHP files.
- Existing `make check` smoke tests pass.
- ZIP archive integrity verified.
- No live production database/browser session was available in this environment; deployment/runtime behavior should be confirmed on the hosted site.
