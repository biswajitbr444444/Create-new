# PocketCat V11.42 — Local GeoIP + Real-Time Link Analytics

## Major changes

- Removed runtime dependency on FreeIPAPI and ipapi.co.
- Added a self-hosted MaxMind DB v2 reader in `includes/maxmind-reader.php`.
- Added local GeoLite2-City support through `geoip/GeoLite2-City.mmdb`.
- Added `geoip/update.php` for authenticated MaxMind downloads or an authorized/public mirror.
- Link redirects resolve country, city, region, postal code, latitude, longitude and timezone locally before routing/tracking.
- Queued click processing reuses the local GeoIP result instead of making a network lookup.
- Advanced website analytics now uses the same local GeoIP database.
- Added local GeoIP status/test screen at `/admin/geo-providers/`.
- Added server-sent event endpoint `/api/link-events/stream/` for real-time click delivery.
- Link analytics now adds live marker pins with IP, city, country and timestamp popups.
- Leaflet zoom controls and wheel zoom are enabled.
- Existing polling endpoint remains as a fallback when EventSource is unavailable.
- Added analytics latitude, longitude, country name and timezone fields for future local maps.

## Privacy / network behavior

Runtime visitor tracking does not send IP addresses to a geolocation provider. GeoIP resolution reads the local database file only.

Map tiles may still use OpenStreetMap through Leaflet because map rendering and IP geolocation are separate services. The application does not send visitor IPs to OpenStreetMap.
