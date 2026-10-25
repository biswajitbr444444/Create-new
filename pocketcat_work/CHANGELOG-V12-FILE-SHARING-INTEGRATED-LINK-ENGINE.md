# PocketCat V12 — File Storage & Sharing Integrated with Links

## Delivered
- File share creation now creates a real `short_links` record and returns `/r/{code}`.
- File share action menu exposes Preview, Edit Link, Customize QR, Share and Link Analytics.
- Link analytics is the existing PocketCat engine (`/link-analytics/?id=...`) with geo/device/referrer/live polling.
- Share pages include OpenGraph metadata, branded theme controls, WhatsApp/Facebook/Instagram/device sharing and QR access.
- Single-file and ZIP downloads enforce share-level thresholds atomically.
- Upload UX uses multi-file queue, per-file progress, chunked local fallback, cloud multipart support and clear errors.
- File dashboard adds grid/table toggle, search, type filter, quota gauge and action menus.
- Added `file_share_events` for share-page lifecycle auditing.
- Security remains server-side: MIME detection, extension blocking, path normalization, prepared statements, CSRF and policy checks.

## Migration
Run `database/migration-v12-file-sharing-link-integration.sql` once on existing installations. Fresh installs get the new columns from `database/schema.sql`.
