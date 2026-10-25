# PocketCat V18.5 — Secure OTA Update Center

- Added a first-party admin OTA Update Center with staged package review and explicit deployment confirmation.
- Added format detection and integrity validation for ZIP, TAR, TAR.GZ/TGZ, TAR.BZ2/TBZ2, 7Z, RAR, TAR.XZ/TXZ, GZ, BZ2 and XZ. External archive utilities are detected at runtime; unsupported host capabilities are reported instead of silently failing.
- Added archive path traversal and symlink rejection, private staging, expansion/file-count limits, selective changed-file deployment and atomic per-file replacement.
- Protected environment/configuration files, app secrets, sessions, runtime storage and user-upload directories from package overwrite.
- Added per-file pre-update snapshots, PHP CLI syntax checks, automatic file rollback on caught failures, and recovery of interrupted deployments when the updater is next opened.
- Added a constrained additive SQL migration path supporting `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE ... ADD COLUMN/INDEX/KEY`, and `CREATE INDEX`; destructive SQL is rejected.
- Added a dedicated error diagnostics panel and clipboard copy action.
- Added admin navigation and a clean URL route; updated the release marker to 18.5.

## Operational notes

- ZIP requires PHP ZipArchive or `unzip`; TAR variants require `tar`; single-file GZ/BZ2/XZ require their decompression utility; 7Z/RAR require `7z`/`7zz` or `unrar` as appropriate.
- Use a hosting-level backup and maintenance window before production updates. No PHP updater can guarantee rollback if the hosting process is killed before it can execute recovery, or if the disk/permissions fail.
- Additive database migrations intentionally are not rolled back; they are restricted to non-destructive schema changes to preserve production data.
