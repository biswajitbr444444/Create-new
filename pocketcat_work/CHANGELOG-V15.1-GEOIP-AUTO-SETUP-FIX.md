# PocketCat V15.1 — GeoIP Auto Setup & Reader Fix

- Added an explicit **Auto Setup / Repair Local GeoIP** action to `/admin/geo-providers/`.
- Auto setup now uses the current `geolite2-city` jsDelivr distribution rather than a stale pinned package URL.
- Hardened the local MaxMind reader so zero-length binary reads are handled safely on current PHP runtimes.
- Kept runtime visitor geolocation fully local; the CDN is used only by the administrator installation/update operation.
- Manual Install / Update remains available separately.
