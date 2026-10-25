# PocketCat V16.7 — Site Editor + Local GeoIP QA Fix

## Scope
V16.7 is based on V16.6 and fixes the concrete issues visible in the latest admin screenshots while preserving the existing database schema and V16.6 AI/GeoIP behavior.

## Changes
- **Local GeoIP install/update form** → a PHP expression was accidentally emitted as literal text (`'.csrf_field().'`) → restored proper server-side CSRF field rendering.
- **Site Editor legacy script** → an obsolete inline editor script referenced removed element IDs and threw a JavaScript error on page load → removed the dead script; the V16.4+ Editor Suite is now the single editor implementation.
- **Global Reset Defaults** → reset action stored empty strings, which prevented configured defaults from being used → reset now restores the complete default configuration.
- **Global Settings fallback** → empty legacy settings could render blank fields after an old reset → editor fields now fall back to the same defaults used by the reset action.
- **Global Settings POST hardening** → partial requests could overwrite omitted settings with empty values → only submitted allowlisted fields are updated. Theme color values are validated server-side.
- **Site Editor mobile containment** → editor toolbars/workspace could contribute to horizontal overflow on narrow screens → editor controls now scroll internally and the suite is width-contained.
- **Asset cache version** → shared app/responsive CSS references updated from V16.6 to V16.7.

## Compatibility
- No database schema changes.
- Existing AI/Bedrock credentials, Local GeoIP database, analytics behavior, CMS data, and editor revision tables remain compatible.
- No destructive migrations.
