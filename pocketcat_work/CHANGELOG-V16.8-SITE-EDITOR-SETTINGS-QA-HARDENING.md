# PocketCat V16.8 — Site Editor Settings QA Hardening

## Scope
V16.8 is based on V16.7 and hardens the Global Branding, Live Platform Activity, and Theme & Colors controls shown in the latest mobile admin screenshot.

## Changes
- **Live Platform Activity → counter inputs** → accepted arbitrary/non-numeric values and saved them directly → normalize counters to non-negative integer strings while preserving valid existing values.
- **Activity save → post-save navigation** → always returned to the top of Site Editor → returns to the Activity section anchor.
- **Theme & Colors → post-save navigation** → returned to the top of Site Editor → returns to the Theme section anchor.
- **Global branding URLs** → URL fields had no server-side validation → validate HTTP/HTTPS URLs while allowing optional social/logo fields to remain blank.
- **Global branding text** → unrestricted input length could create oversized settings → apply bounded lengths to names, metadata, footer text, and navigation labels.
- **Growth base / growth rates** → values were stored without validation → normalize the base counter and validate comma-separated rates from 0–100, with safe fallback to defaults.
- **Hex colors** → server validation existed only partially across the duplicated editors → retain strict six-digit hex validation for all theme color settings.
- **Asset cache version** → V16.7 cache identifiers remained → updated shared application/responsive CSS cache references to V16.8.

## Compatibility
- No database schema changes.
- No destructive migrations.
- Existing settings keys remain unchanged.
- Existing AI/Bedrock, Local GeoIP, analytics, CMS, file/media, link, and editor data remain compatible.
