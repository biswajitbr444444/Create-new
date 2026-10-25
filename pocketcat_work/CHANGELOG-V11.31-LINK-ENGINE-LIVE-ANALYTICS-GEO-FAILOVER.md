# PocketCat V11.31 — Link Engine + Live Analytics + Geo-IP Failover

## Fixed
- Removed the Create Link → QR Editor forced redirect.
- Create Link now supports an inline AJAX response with short URL, copy, QR, share and history insertion.
- Added strict validation for custom aliases, expiration, max clicks and country codes.
- Fixed analytics hour/click rendering so time and quantities remain separate values.
- Reworked mobile Site Settings form spacing to prevent checkbox/description/action overlap.
- Added responsive analytics tables, cards and map sizing.

## Live click stream
- Visitor clicks are queued before the redirect and no external Geo-IP lookup runs in the redirect request.
- Added `cron/link_click_worker.php` to enrich queued events asynchronously.
- Captures referrer, Accept-Language, UTM parameters, device, browser/version/engine, OS/version, bot type and Cloudflare/proxy-aware client IP.
- Captures country, region, city, postal code, latitude/longitude, timezone, ISP and ASN after Geo-IP enrichment.
- Added authenticated `/api/link-events/` polling endpoint and live event table.
- Added bot/crawler filter toggle.

## Geo-IP provider failover
- Added `geo_providers` and automatic default providers.
- Default providers: FreeIPAPI and ipapi.co.
- Added priority ordering, provider testing, enable/disable, API key storage, timeout and configurable cooldown.
- 429 and provider failures automatically enter cooldown and the next active provider is tried.
- Added `/admin/geo-providers/` management UI.

## Deployment
Run the click worker every minute (or more frequently if the hosting plan permits):

```cron
* * * * * /usr/bin/php /path/to/PocketCat/cron/link_click_worker.php 100 >/dev/null 2>&1
```

The worker is intentionally CLI-first and should not be exposed as a public web endpoint.
