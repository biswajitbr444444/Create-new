# V11.0 — Enterprise Cloud-Integrated Editor Foundation

- Added enterprise V11 architecture and explicit control-plane/data-plane boundary.
- Added multi-file workspace shell, tabs, file tree and workspace ZIP export.
- Added encrypted workspace snapshot export using WebCrypto AES-256-GCM/PBKDF2.
- Added provider abstraction foundation for Firebase and local storage.
- Hardened browser vault device key to non-exportable AES-GCM.
- Added chunk-safe base64 handling for large encrypted payloads.
- Added Monaco and JSZip integration points.
- Added runtime/security design documentation.

Compatibility: V10.4 APIs remain available. The workspace layer is intentionally introduced alongside the legacy editor so migration can be tested without destructive changes.
