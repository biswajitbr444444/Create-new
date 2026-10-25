# PocketCat V18.5 OTA updater operations

## Open the updater

Sign in as an administrator, enter Admin View, and open **Administration → OTA Update Center** (`/admin/ota-updater/`). Upload a release archive, review the staged file list and additive migrations, then explicitly apply or discard it.

## Hosting portability

Application paths are resolved relative to the deployed source files using PHP `__DIR__`; no `public_html`, `htdocs`, or provider-specific absolute path is assumed. Private staging and snapshots live in `storage/ota/`, which is denied by the site's `.htaccess` rules. The existing application upload engine continues to resolve upload directories relative to `includes/upload-engine.php`.

## Archive tools

- ZIP: PHP `ZipArchive` or `unzip` plus `zipinfo` for safe entry-type inspection.
- TAR, TAR.GZ/TGZ, TAR.BZ2/TBZ2, TAR.XZ/TXZ: `tar`.
- Standalone GZ, BZ2 and XZ: `gzip`, `bzip2` and `xz` respectively.
- 7Z and RAR: `7z`/`7zz`, or `unrar` for RAR.

The updater reports unavailable utilities during validation. Shared hosts may not permit installing external binaries, so archive support depends on what the provider exposes.

## Safety model

The updater validates archive integrity, rejects path traversal and links, limits package and expanded size, excludes configuration/secrets/session/storage/upload paths, skips unchanged files, snapshots replaced files, atomically replaces files, runs PHP CLI syntax checks, and performs post-update checks on essential application files and upload directories. A caught failure triggers file rollback. If PHP is terminated mid-deployment, the updater detects an `applying` snapshot and attempts recovery the next time an administrator opens the updater.

SQL migration files belong in `migrations/*.sql` at the package root. Only additive `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE ... ADD COLUMN/INDEX/KEY`, and `CREATE INDEX` statements are accepted. Migrations are not automatically reversed because schema rollback could destroy production data.

Always keep a provider-level backup and use a maintenance window for production updates. No in-process updater can guarantee recovery after disk failure, revoked filesystem permissions, or a hosting process kill before recovery code can execute.
