# PocketCat V19.2 deployment notes

## What changed

V19.2 hardens source-root discovery and OTA update handling. Application and upload paths are resolved from the installed source tree, so the application does not assume a specific hosting folder such as `public_html` or `htdocs`. The OTA staging flow now validates the original archive filename after moving the upload into private staging storage, which fixes archive detection on common PHP upload implementations.

## Deploying the full ZIP

1. Back up the complete application directory and database using your hosting provider's tools.
2. Extract the package into the existing PocketCat application root. Do not replace the live `.env`, `config.php`, user upload directories, or session storage with package contents.
3. Confirm the hosting account permits PHP to create/write `storage/` and `uploads/`; PocketCat initializes required runtime directories automatically.
4. Sign in as an administrator and open **Administration → OTA Update Center** to stage and review future update packages.
5. Run upload, file-sharing, media-library, and login checks on a staging deployment before production rollout.

## Archive support

The updater detects ZIP, TAR, TAR.GZ/TGZ, TAR.BZ2/TBZ2, TAR.XZ/TXZ, GZ, BZ2, XZ, 7Z, and RAR. ZIP/TAR/GZ/BZ2/XZ support uses the available PHP extension or standard host utilities. 7Z and RAR require `7z`/`7zz` or `unrar`; if a host does not provide those binaries, the updater reports that dependency rather than silently treating the format as supported.

## Migration and rollback safety

Place release SQL migrations in `migrations/*.sql`. Only additive table creation and column/index additions are accepted. File snapshots are created before replacements, changed PHP files are syntax checked, and caught failures trigger file rollback. Additive schema changes already executed are retained on rollback so the updater never attempts a potentially destructive reverse migration. Keep a provider-level database and filesystem backup because a process kill, disk failure, or revoked permission may prevent in-process recovery.
