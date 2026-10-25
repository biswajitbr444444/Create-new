# PocketCat V11.43 — GeoIP Lookup + Analytics Connection + Feature Cleanup

- Fixed the bundled MaxMind DB reader pointer decoding so local GeoLite2 lookups can resolve records correctly when the PHP MaxMind extension is unavailable.
- Fixed the Local GeoIP status metadata path to use the same reader implementation as runtime lookups.
- Removed repeated EventSource “Reconnecting” loops from Link Analytics; live events now use stable 5-second JSON polling with offline/retry status.
- Permanently removed Review Analytics / Review Intelligence user and admin UI, routes, API, and Premium messaging. Existing historical review tables are not dropped automatically.
- Permanently removed My Storage / Storage Settings / File Storage Settings UI and routes. File Storage & Sharing itself remains available using the existing backend configuration.
