# PocketCat V11.12 — Cloud Storage, Moderation, AI Admin & Media

## Included
- File moderation IP blocks: 1h, 6h, 24h, 1 week, 1 month, 1 year, permanent.
- Direct unblock actions and persistent block/unblock audit history with clear-history control.
- Encrypted storage of newly recorded moderation IP addresses.
- File Storage drivers: S3/R2, Google Drive, Microsoft OneDrive, Dropbox and InfinityFree Local fallback.
- OAuth connection flow for Google Drive, OneDrive and Dropbox with encrypted access/refresh tokens.
- Configurable backup/storage folder; provider-aware settings UI.
- Provider server-upload path for connected cloud accounts; S3 remains direct-browser upload.
- Human-readable storage/file limits (KB/MB/GB/TB) in settings.
- Bedrock authentication selector: API Key, AWS IAM, Cloudflare Worker Relay.
- OpenAI-compatible Bedrock UI focuses on Base URL, Model ID/Deployment Name and API Key.
- Customer AI Access table redesigned with horizontal scrolling, bounded credential boxes, copy controls, CSV export and inline editing of non-secret parameters.
- Raw customer secrets remain masked and are not exported.
- Media Library is accessible to authenticated platform users, with bulk upload, folders, preview modal, public URL copy and admin-only deletion.
- Existing V11.11 navigation/docs/mobile fixes preserved.

## Security
- OAuth tokens and cloud client secrets use PocketCat encrypted-secret storage.
- Customer AI secret values are never returned in the admin customer table or CSV.
- CSRF protection remains enabled for state-changing actions.
