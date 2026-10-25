# PocketCat Local GeoIP

PocketCat V11.42 performs runtime IP geolocation entirely from the local `GeoLite2-City.mmdb` file. No `ipapi.co`, FreeIPAPI, or other geolocation HTTP service is queried by the application.

## Install / update the database

MaxMind provides GeoLite2 for free, but current database downloads require a MaxMind account and license key. On a server with PHP CLI and cURL:

```bash
MAXMIND_ACCOUNT_ID="YOUR_ACCOUNT_ID" MAXMIND_LICENSE_KEY="YOUR_LICENSE_KEY" php geoip/update.php
```

The updater downloads the database once, extracts `GeoLite2-City.mmdb`, and leaves the runtime completely offline.

If no MaxMind credentials are supplied, the updater defaults to a public GeoLite2 distribution mirror and stores the result locally. You can override it with `GEOIP_FALLBACK_URL` for an authorized mirror. This network access happens only during database installation/update; visitor tracking never calls the mirror.

The database is read-only at runtime. PocketCat stores only the analytics fields needed by the application and does not call a GeoIP web API.

## Attribution / licensing

GeoLite2 data is created by MaxMind. Keep the applicable GeoLite2 attribution and license files with the database. See MaxMind's GeoLite documentation for current redistribution terms.
