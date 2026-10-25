# PocketCat V11.6 — Current InfinityFree Hosting

## Current hosting details
- Website/domain: `pocketcat.online`
- Domain document root: `pocketcat.online/htdocs`
- InfinityFree account: `if0_42811059`
- Website IP: `185.27.134.215`
- Home directory: `/home/vol2_8/infinityfree.com/if0_42811059`
- Database host: `sql104.infinityfree.com`
- Database name: `if0_42811059_pocketcat_online`
- Database username: `if0_42811059`
- Database port: `3306`
- Website URL: `https://pocketcat.online`
- Bedrock relay: `https://orange-lake-6872.biswajitw111111.workers.dev`

> **Secret handling:** The MySQL password is deliberately not stored in this package. Enter it in the installer or runtime secret configuration. Do not commit it to source control.

Passwords, Bedrock API keys, and relay tokens are intentionally not bundled. Enter them during the first-run installer.

## InfinityFree upload
1. Open the `pocketcat.online/htdocs` directory for the domain.
2. Upload this ZIP and extract it so `index.php` is directly inside `htdocs` (not inside another PocketCat folder).
3. Open `/install/`.
4. Step 1: confirm the pre-filled database host/name/username and enter the current MySQL password.
5. Step 2: build the database schema.
6. Step 3: create the administrator account and choose a fresh password.
7. Step 4: keep Bedrock transport on Relay and enter the Worker relay token; enter the Bedrock API key only if PHP direct fallback is desired.
8. Finish setup.

Do not upload or expose `config.php`, `bootstrap.php`, `.env`, or installer secrets publicly. The included `.htaccess` blocks sensitive PHP/config files.

## Enterprise Control Plane

After installation, an administrator can open `/admin/control-plane/` to publish plan and governance changes. The matrix is stored in MySQL and evaluated at request time. No new ZIP deployment is needed for routine quota, feature or restriction changes.

Recommended scheduled workers:
- `cron/file_cleanup.php` at least every 15 minutes
- `cron/file_scan.php` as frequently as the malware scanner SLA requires
- object-storage lifecycle rules as a second cleanup layer

## V11.10 notes
- Developer API now has an explicit `/settings/link-api/` rewrite.
- File Storage prefers S3/R2 when configured. If S3/R2 is not configured, files up to the host PHP upload limit use the local InfinityFree fallback so small uploads do not fail with `Object storage is not configured`.
- Configure S3/R2 for large files and production-scale storage; the local fallback is intentionally limited by PHP hosting limits.
- Support Chat accepts multiple attachments (each subject to the host upload limit) and supports removing selected files before send.
