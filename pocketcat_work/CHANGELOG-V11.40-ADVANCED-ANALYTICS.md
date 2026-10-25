# PocketCat V11.40 — Advanced Website Analytics & Tracking

- Added Premium-only first-party website analytics and tracking.
- Added live visitors, sessions, unique visitors, page views, engagement, UTM acquisition, top pages, devices, browsers, countries, custom events and conversion goals.
- Added privacy-safe consent and HMAC IP/visitor/session hashes.
- Added user analytics dashboard and admin cross-site analytics dashboard.
- Added customer review collection and review intelligence with source attribution, ratings and sentiment classification.
- Added user and admin review dashboards.
- Added tracking snippet and browser event collector.
- Added V11.40 database migration.
- Added pricing/billing messaging for Premium analytics and review intelligence.
- Added deployment/privacy documentation.

## V11.41 — Installer Database Build Reliability
- Fixed first-run schema build failures caused by partially-created databases and duplicate DDL on retry.
- Made installer schema execution idempotent for existing tables, columns and index names.
- Consolidated current link-click analytics fields into the base schema.
- Consolidated current media/file-sharing fields into the base schema.
- Added server-side error logging for real schema/permission failures.
