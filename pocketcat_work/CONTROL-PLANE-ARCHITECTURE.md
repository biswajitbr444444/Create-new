# PocketCat Enterprise Unified Governance / Control Plane

## Architecture

PocketCat uses a database-backed Dynamic Policy Engine. The effective policy is resolved on every request from `system_settings.policy_engine`, the user's current tier, and active rows in `policy_user_overrides`. No PHP deployment is required after an administrator publishes a policy change.

```text
                    Super Admin UI
                          |
                    policy_engine JSON
                          |
              +-----------+-----------+
              |                       |
        Plan Matrix              User Overrides
              |                       |
              +-----------+-----------+
                          |
                  Effective Policy
                    /           \
             URL Engine      File Engine
                |                 |
          Redirect/API       Upload/Download
                |                 |
        Global network rules + kill switches
```

## Tier defaults

### Free
- 50 URL links/month
- 301 redirects
- 30-day analytics
- 100 MB/file, 1 GB storage
- 24-hour maximum expiry
- no URL/file password, burn-after-reading, deep links, geo routing, API, webhooks, custom QR
- QR watermark and download-ad flags enabled

### Basic
- 500 URL links/month
- 301 redirects
- 90-day analytics
- 2 GB/file, 25 GB storage
- 14-day maximum expiry
- password protection, custom QR, bulk CSV
- OS/country routing
- media/PDF previews

### Premium
- unlimited URL links
- 1 TB configurable storage
- 20 GB/file
- unlimited expiry by default
- city routing, deep links, retargeting pixels, API/webhooks, heatmaps
- burn-after-reading, resumable upload, permanent storage and CDN flags

## Governance model

- **Global flags:** emergency kill switches for URL redirects, URL creation, file uploads/downloads, previews, API, webhooks, QR and maintenance.
- **Global limits:** redirect/API rate limits, upload concurrency, multipart size, bandwidth controls and log retention.
- **Network rules:** IP, CIDR and country block rules.
- **User overrides:** arbitrary plan-policy keys can be granted/revoked for a single user, optionally with an expiry.
- **RBAC:** custom roles are stored in `admin_roles` and assigned through `user_admin_roles`.
- **Audit:** application-level append-only `policy_audit_logs` records admin ID, IP, action, before and after payloads.
- **Impersonation:** session-bound target identity with start/stop audit records.

## Control-plane URL

`/admin/control-plane/`

The control plane contains:

1. Dynamic plan matrix
2. Global kill switches
3. Global rate/retention controls
4. White-label metadata
5. IP/CIDR/country blocking
6. Central link/file registry
7. User plan/status governance
8. Password reset-link generation
9. Asset purge
10. Secure impersonation
11. Per-user feature/quota overrides
12. Custom admin RBAC
13. API token revocation
14. Failed webhook delivery view
15. Immutable audit view

## Security boundaries

- All state-changing admin operations require CSRF validation.
- Secrets are not rendered in the UI.
- Policy mutation is audited before redirecting back to the control plane.
- The current administrator account cannot be banned through the user-governance controls.
- Impersonation cannot target the initiating administrator.
- URL redirects honor global network blocks and the dynamic redirect kill switch.
- API requests honor the global API kill switch and dynamic rate limits.
- File public delivery honors the file-download kill switch and network blocks.
- URL password, deep-link, pixel and geo features are dynamically re-evaluated against the link owner's current effective policy.
- File password and burn features are dynamically re-evaluated against the file owner's current effective policy.

## Storage lifecycle

The existing `cron/file_cleanup.php` worker now consumes dynamic log-retention policy and removes expired/deleted database records and corresponding S3/R2 objects when storage credentials are configured. External object-storage lifecycle policies remain recommended for defense in depth.

## Threat intelligence

The existing Safe Browsing integration remains the URL safety gate. The control plane can pause/remap/purge links globally. A production deployment should use fail-closed behavior for high-risk tenants if threat-intelligence availability is a contractual security requirement.

## Global latency note

The PHP/MySQL control plane is intentionally the control plane, not the global data plane. For a true global `<100 ms` redirect target, put the redirect hostname behind Cloudflare Workers/edge cache and use this policy store as the origin configuration source. The application itself does not claim a universal `<100 ms` SLA from shared hosting alone.
