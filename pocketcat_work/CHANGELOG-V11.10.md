# PocketCat V11.10 — Upload, Search, API & Secret UX Fixes

- Added InfinityFree-safe local file-storage fallback when S3/R2 is not configured; S3/R2 remains preferred.
- Added multi-file File Storage uploads with per-item remove controls and clear hosting-limit messaging.
- Added Media Library to the workspace menu and a personal `/media/` library.
- Fixed `/settings/link-api/` rewrite so Developer API no longer lands on InfinityFree 404.
- Improved workspace search so user-visible settings are searchable even in User View.
- Added multi-file Support Chat attachments with remove controls and JSON-safe API error handling.
- Added Bank Transfer alongside UPI, PhonePe, Google Pay and Net Banking.
- Improved Plans & Billing desktop grid responsiveness.
- Added masked secret controls with Eye/Hide and Copy actions for Bedrock, relay, storage, SMTP and Safe Browsing secrets.
- Saved masked sentinel values are never re-encrypted.
