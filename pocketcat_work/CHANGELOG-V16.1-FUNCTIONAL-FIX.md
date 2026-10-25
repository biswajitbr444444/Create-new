# PocketCat V16.1 — Functional Reliability Fix

V16.1 is a functional-fix build focused on buttons, uploads, support messaging, API controls, and resilient transport.

## Fixed
- Media Choose Files / Choose Folder with mobile-friendly folder selection and drag/drop.
- Resumable media uploads with status probing, retries, progress, and two-file concurrency.
- Support attachments use the resumable uploader and retry failed chunks.
- Plain support messages are no longer blocked by the file-upload policy flag.
- Added `/api/support/` compatibility endpoint for shared-host/WAF environments.
- Support send/poll requests use same-origin credentials and structured JSON errors instead of exposing raw HTML error pages.
- Upload API now supports session status/resume checks.
- Existing API token, webhook, analytics, and live-tail controls remain wired to the existing database actions.
- AI configuration keeps relay/direct modes separated and surfaces transport errors safely.

## Deployment
Upload the complete V16.1 package over the existing PocketCat installation. The normal schema bootstrap remains backward-compatible; no destructive migration is included.
