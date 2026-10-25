# PocketCat V19.73 — Public Copy, Bedrock and Policy Polish

V19.73 removes duplicated copy from the 20-section About page, clarifies the optional Amazon Bedrock integration and server-side credential handling, tightens cookie-policy language, standardizes the support contact, and adds an AWS Activate/production-readiness checklist. This release does not claim AWS Activate approval or certify legal compliance; deployment-specific checks remain necessary.

See `CHANGELOG-V19.73-AWS-ACTIVATE-PUBLIC-POLISH.md` and `docs/AWS-ACTIVATE-READINESS.md`. The application version is recorded in `VERSION`.

---

# PocketCat V19.2 — Universal Path Resilience & Secure OTA Updates

V19.2 improves hosting portability by resolving application and upload paths from the deployed source tree, fixes archive staging on PHP hosts, hardens OTA deployment against symlink traversal and concurrent updates, bounds standalone compressed-file extraction, and updates release notes and diagnostics. Existing application functionality and database data are preserved; migrations remain additive-only.

See [V19.2 deployment notes](README-V19.2-DEPLOYMENT.md), [V19.2 changelog](CHANGELOG-V19.2-UNIVERSAL-PATH-RESILIENCE-OTA.md), and [V19.2 QA report](QA-REPORT-V19.2.md).

---

## Historical README

# PocketCat V17.4 — Advanced Upload System

V17.4 upgrades File Storage & Sharing with live network speed, upload size, explicit 0–100% progress, retry, cancel, resumable recovery, and automatic duplicate filename handling. The V17.3 administrator Version Details routing fix remains preserved.

No database schema migration is required. Existing file objects, storage drivers, sharing, analytics, downloads, media and authentication behavior remain compatible.


## V19.5 update

See `CHANGELOG-V19.5-FILE-SHARE-DOWNLOAD-RESPONSIVE-FIX.md` for the shared-file download token validation fix, archive response validation, and responsive File Storage header repair. Project version is recorded in `VERSION`.
