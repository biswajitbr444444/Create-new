# PocketCat Enterprise File Sharing & Storage Engine

## Architecture

**Browser → PocketCat control plane → S3/R2 object storage**

The PHP application only handles authentication, metadata, presigned URL issuance, policy enforcement and analytics. File bytes are uploaded directly to S3-compatible storage. Large files use S3 multipart upload with 100 MB parts and can resume by re-requesting a part URL.

### Storage
- AWS S3 and Cloudflare R2 compatible endpoint
- Original bytes are stored without application compression
- Provider-side encryption at rest should be enabled (S3 SSE-S3/AES-256 where supported; R2 encrypts stored objects at rest)
- Bucket should remain private; public links are PocketCat share pages backed by short-lived presigned GET URLs

### Security model
- Share tokens are random, unguessable identifiers
- Optional password gate uses PHP password hashing; passwords are never stored plaintext
- Access pages enforce expiry and download limits before issuing a signed object URL
- Extension/MIME blacklist is enforced before upload initialization
- Bot filtering and hashed visitor/IP analytics are used to reduce tracking data exposure
- Admin can quarantine/delete files and block abusive IPs

**Important:** a password gate is not the same as client-side end-to-end file encryption. The current module uses secure access control plus provider encryption at rest. True client-side E2EE would require browser-side encryption before upload and would change preview/search/streaming semantics.

## Upload API

### Browser/session API
- `POST /api/files.php?action=create` — returns a presigned single PUT URL or multipart upload information.
- `POST /api/files.php?action=complete` — finalizes an upload and puts it into security scanning.
- `POST /api/files.php?action=part_url` — returns a signed multipart part URL.
- `POST /api/files.php?action=delete` — soft-deletes metadata; cleanup removes the object.

### REST-style API
- `POST /api/files/` — create upload session (Bearer token)
- `GET /api/files/{id}` — status
- `POST /api/files/{id}/complete` — complete multipart upload
- `DELETE /api/files/{id}` — delete
- `GET /api/files/{id}/stats` — analytics
- `GET /api/files/{id}/analytics.csv` — CSV analytics export
- `POST /api/files/tokens` — create API token
- `POST /api/files/webhooks` — register webhook

## Webhooks
Supported event names:
- `file.uploaded`
- `file.downloaded`
- `file.expired`
- `quota.exceeded`

Payloads are signed with HMAC-SHA256 in `X-PocketCat-Signature`.

## Analytics
Tracked events include view, download start and client completion signal. Country/city are read from Cloudflare headers when present. User-agent is classified into browser, OS and device. Known bots and social scrapers are excluded from aggregate download metrics.

Because direct-to-object-storage downloads bypass PHP, an origin server cannot know with absolute certainty when the browser finished receiving every byte. PocketCat records download start plus a client completion signal. For authoritative completion metrics, integrate S3/R2 access logs, object events, or a streaming proxy.

## Malware scanning
InfinityFree shared hosting does not provide a local ClamAV daemon. The module therefore supports an external scanner webhook:

1. Upload completes to private S3/R2.
2. Cron worker creates a short-lived signed GET URL.
3. Worker POSTs metadata and the signed URL to `file_scanner_url`.
4. Scanner returns `{ "clean": true }` or `{ "infected": true }`.
5. Only `clean` objects become downloadable.

If no scanner is configured, files remain pending rather than being silently declared malware-free. An admin can manually mark a file clean from **Admin → File Moderation**.

## Cron
Configure hosting cron jobs to run:

```text
php /home/.../cron/file_scan.php
php /home/.../cron/file_cleanup.php
```

Run the scan worker every 5–10 minutes and cleanup hourly/daily.

## S3/R2 CORS
Allow the PocketCat origin to PUT objects and expose `ETag` for multipart completion. A typical policy is:

```json
[
  {
    "AllowedOrigins": ["https://pocketcat.online"],
    "AllowedMethods": ["GET", "PUT", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag", "Content-Length"],
    "MaxAgeSeconds": 3600
  }
]
```

## Configuration
Storage configuration is now internal to the File Storage backend; the former Storage Settings / My Storage screens are no longer exposed. File Storage & Sharing continues to use the configured backend/default local storage.
- S3-compatible endpoint
- region (`auto` for R2, AWS region for S3)
- bucket
- access key
- secret key
- optional scanner webhook
- per-plan file and storage byte caps

Secrets are encrypted using the application's AES-256-GCM secret store.

## Preview / QR
The share page supports image, video, audio, PDF and text/code preview. QR generation is client-side and can export SVG/PNG with size and logo controls. The QR helper uses a CDN-hosted QR encoder; replace it with a locally bundled library if the deployment must be fully offline.

## Global latency
The application deliberately separates the control plane from the data plane. For a genuine global <100 ms redirect/download-page SLA, place the public file/share hostname behind Cloudflare or another edge network and keep the S3/R2 bucket private. InfinityFree PHP alone cannot guarantee a global latency SLA.

## V12 Link & Analytics Integration

File Storage & Sharing is an integrated workspace module, not a separate sharing product. A share action creates a real `short_links` record whose destination is the PocketCat share page. The resulting `/r/{slug}` therefore uses the existing redirect, GeoIP, device, referrer, UTM, routing, pixels, OpenGraph and live analytics engine.

The file dashboard exposes Preview, Edit Link, Customize QR, Share, Analytics and Delete from one action menu. Public share pages support in-browser image/PDF/audio/video/text previews, password protection, expiry, view-only access, download thresholds, branded theme controls, OpenGraph metadata, QR access and native/social sharing.

Existing installations must run `database/migration-v12-file-sharing-link-integration.sql` after deploying this version.
