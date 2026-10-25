# PocketCat URL Shortening & Link Management Engine

## Production architecture

**Fast path:** Browser → `/r/{slug}` → Apache rewrite → `links/redirect.php` → indexed MySQL lookup → routing decision → 301/307.

For global sub-100ms latency, put the redirect hostname behind **Cloudflare Workers/Cache** and cache the slug→destination record at the edge. The InfinityFree PHP path is the compatibility/origin implementation; it cannot itself guarantee global <100ms latency.

### Data model
- `short_links`: mutable destination, stable slug, password/expiry/click limits, OS/country/city routing, deep links, UTM, pixels and OG metadata.
- `link_clicks`: append-only analytics event rows with hashed visitor/IP identifiers, UA-derived dimensions, referrer, Cloudflare geo headers and bot flag.
- `link_api_tokens`: hashed Bearer tokens; raw tokens are shown once.
- `link_webhooks` + `link_webhook_deliveries`: click, milestone and expiry notifications with HMAC signatures.
- `link_rate_limits`: MySQL fallback limiter; APCu is used automatically when available.

### Redirect semantics
- 301 is used for the normal cached redirect path.
- 307 is used around configured milestone thresholds to preserve method semantics for clients that need it.
- Password-protected links use a short-lived session gateway.
- OS routing supports iOS, Android, Windows and macOS.
- Geo routing consumes Cloudflare `CF-IPCountry`/`CF-City` headers when present.
- Deep links can use custom schemes or `intent://`; a web fallback is supported.
- UTM parameters are appended server-side.
- Marketing pixels intentionally use an intermediate screen; this is opt-in and slower than the pure redirect path.
- Social/search crawlers are excluded from click metrics and can receive OG metadata without counting as human traffic.

### Analytics
The analytics page provides total clicks, unique visitors, referrers, browsers, OS, devices, countries, cities, hourly distribution, weekday distribution, CSV export and a printable PDF export. Country data is visualized using a client-side world map.

### Developer API
- `GET /api/links/`
- `POST /api/links/`
- `GET /api/links/{id}`
- `PATCH /api/links/{id}`
- `DELETE /api/links/{id}`
- `GET /api/links/{id}/stats`
- `POST /api/links/webhooks`

Authentication: `Authorization: Bearer pc_live_…`

### QR suite
The QR page generates customizable QR codes in the browser with SVG/PNG export, foreground/background color, resolution and optional center logo URL. It uses a CDN-hosted QR encoder to avoid a large PHP dependency on InfinityFree.

### Threat intelligence
Google Safe Browsing is optional. Configure the API key under **Admin → Security**. The engine checks destinations during create/update and blocks confirmed threat matches.

## Important hosting limitation
InfinityFree shared hosting is suitable as the application origin, but **do not claim that PHP/MySQL on shared hosting alone guarantees <100ms globally**. For that SLA, deploy the redirect hot path to Cloudflare Workers/edge storage and use PocketCat PHP/MySQL as the control plane and analytics sink.
