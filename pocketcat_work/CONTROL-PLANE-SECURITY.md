# Control Plane Security Model

## Trust zones

1. Browser / public edge: untrusted input, public redirect and file-share endpoints.
2. Application plane: PHP request validation, policy evaluation, authorization, rate limiting.
3. Control plane: Super Admin-only policy mutations and governance actions.
4. Data plane: S3/R2 objects and edge delivery.
5. Audit plane: append-only policy and impersonation logs.

## Administrative protections

- CSRF on all mutation forms.
- Prepared statements for all database writes.
- Passwords remain one-way hashed; secrets are encrypted at rest by the existing PocketCat secret envelope.
- API tokens are stored only as SHA-256 hashes and are shown once at creation.
- Impersonation uses a server-side session target and produces start/stop audit records.
- Self-ban/self-purge/self-demotion paths are protected for the initiating administrator.
- Policy updates capture before/after payloads and administrator IP address.

## Runtime enforcement

The URL redirect path checks the global redirect kill switch, network rules, rate limit and link status before redirecting. URL feature permissions are re-evaluated against the link owner's current effective policy.

The file share path checks global file-download availability, network rules, file scan state, expiry and current owner feature permissions. Upload API calls check the current owner tier, per-file size, storage quota, MIME/extension firewall and expiry policy.

## Production hardening recommendations

- Put public redirect and download hostnames behind Cloudflare Workers/CDN.
- Keep S3/R2 buckets private; issue short-lived signed URLs.
- Enable provider-side encryption and object lifecycle policies.
- Use an external malware scanning service or isolated ClamAV worker for production.
- Restrict the Super Admin route with MFA/SSO at the edge when available.
- Ship audit records to immutable external logging/SIEM for contractual compliance.
