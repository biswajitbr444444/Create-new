# PocketCat V19.2 — Universal Path Resilience & OTA Hardening

## Changes

- Resolve the application root from the installed source tree using `realpath()` instead of relying on hosting-specific document-root names.
- Centralize upload-engine storage path construction and validate relative path segments before creating upload directories.
- Fix OTA staging so archive detection uses the preserved uploaded filename/extension. Previously, the PHP temporary upload filename was checked first and commonly had no extension, causing valid packages to be rejected.
- Add destination-path validation that rejects symbolic-link traversal and any path resolving outside the application root.
- Add a non-blocking deployment lock to prevent concurrent OTA applies.
- Bound standalone GZ/BZ2/XZ decompression while streaming output to disk to reduce decompression-bomb memory risk.
- Improve staging cleanup after failed package validation and format technical error diagnostics as readable multi-line text.
- Correct the installed version marker and updater UI to V19.2.
- Keep the existing additive-only migration policy, affected-file snapshots, syntax checks, post-update health checks, and rollback behavior.

## Operational notes

- ZIP, TAR, TAR.GZ, TAR.BZ2, TAR.XZ, GZ, BZ2, and XZ were exercised in the local test environment.
- 7Z and RAR require a server-provided `7z`/`7zz` or `unrar` utility; these tools were not available in the local test environment.
- The updater cannot guarantee recovery from disk failure, revoked permissions, a host forcibly terminating PHP, or a network/server outage outside the PHP process. Continue to keep a hosting-level backup before production deployments.
- Upload and application directories are rooted relative to the deployed source tree. This avoids assumptions about `public_html`, `htdocs`, or a hosting provider's absolute path.
