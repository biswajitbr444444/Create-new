# V18.8 Bulk Download & Quick Delete — Integration Notes

## Request flow

- Authenticated dashboard actions call `/api/files.php?action=zip` with the existing session and CSRF token. The backend filters every selected `file_objects` row by the authenticated `user_id` and clean status before reading storage.
- Public share actions call the same endpoint with `share_token`. The backend resolves the share, validates expiry/password/view-only/download limits, and joins the selected IDs against `file_share_items`; arbitrary IDs outside the share are rejected.
- Archive filenames are normalized from relative paths; path traversal segments and unsafe path characters are removed. Temporary workspaces are created with restrictive permissions and cleaned on normal completion/error.
- Bulk deletion is authenticated only, validates the IDs against the current user's records, handles each item independently, and returns `{ok, deleted, failed, count, requested}`. Internal exception details are logged server-side, not returned to the client.

## API shapes

`POST /api/files.php?action=zip`

```json
{"ids":[12,13],"mode":"single","format":"tar.gz"}
```

- `mode`: `single` or `separate` for server-side archives.
- `format`: `zip`, `tar`, `tar.gz`, `tar.bz2`, `7z`, `rar`, `tar.xz`, `gz`, `bz2`, or `xz`.
- Success: streamed attachment with `Content-Disposition` and `X-Content-Type-Options: nosniff`.

`POST /api/files.php?action=bulk_delete`

```json
{"ids":[12,13]}
```

- Success or partial success returns JSON `{ok:boolean, deleted:number[], failed:[{id:number,error:string}], count:number, requested:number}`.
- Maximum request size is 500 IDs. Existing CSRF, rate-limit, network-policy, authentication and ownership checks remain in force.

## Operations

- PHP extensions: PDO driver for the configured database, cURL for cloud storage, and `ZipArchive` for ZIP output.
- Archive utilities: `tar`, `gzip`, `bzip2`, `xz`; optional `7z` for `.7z` and `rar` for `.rar`.
- Configure PHP/web-server timeouts and private temp-disk quotas for large archives. For very large production workloads, move archive creation to a persistent job queue and issue expiring one-time download tokens; this patch retains the existing synchronous endpoint to avoid requiring an unprovided queue/database migration.
- Original-file mode downloads individual files and preserves original names/extensions. Use archive mode when hierarchy preservation is required.
