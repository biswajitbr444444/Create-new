# PocketCat V16.6 — AI + Local GeoIP Responsive & Functional Hardening

## Scope
V16.6 is based on V16.5 and hardens the AWS Bedrock and Local GeoIP pages shown in the QA screenshots without changing the database schema.

## Changes
- **AWS Bedrock responsive layout** → credential grids could become cramped at tablet/narrow desktop widths → AI connection fields now collapse cleanly while preserving the effective-connection and action cards.
- **AWS Bedrock secret persistence** → server-side POSTs could theoretically submit inactive connection credentials even when the UI disabled them → only secrets belonging to the selected transport are persisted; inactive credentials continue to be cleared.
- **AWS Bedrock touch controls** → eye/copy and form controls could be difficult to operate on touch screens → minimum touch-friendly control heights are applied on coarse-pointer devices.
- **Local GeoIP responsive layout** → four statistic cards and two-column action forms become cramped on narrower desktop/tablet viewports → statistics and action forms reflow to two columns and then one column on phones.
- **Local GeoIP long database paths/results** → long filesystem paths and JSON results could visually compete with labels → metric rows and lookup output now wrap safely without horizontal page overflow.
- **Page-specific containment** → AI and GeoIP content could inherit generic layout constraints → dedicated `.ai-page` and `.geo-page` containers provide predictable responsive behavior.
- **Asset cache version** → shared CSS references were V16.5 → updated to V16.6.

## Compatibility
- No database schema changes.
- Existing encrypted AI secret storage remains compatible.
- Existing GeoLite2-City database path and lookup behavior remain unchanged.
- Existing CMS/editor and other application features are untouched except for shared CSS cache versioning.
