# PocketCat V11.44 — File Upload Fix + UI Cleanup

- Fixed the File Storage upload creation SQL so `storage_driver`, `status`, `scan_status`, password, expiry and download controls are stored in the correct columns. This was causing local chunk uploads to fail because newly created records could be stored with an invalid storage driver.
- Preserved the hosting-fallback chunked uploader for servers with small PHP `upload_max_filesize` / `post_max_size` limits.
- Kept detailed upload diagnostics and request references for genuine server-side failures.
- Current user sidebar no longer exposes Review Analytics or My Storage.
- Current admin sidebar labels the local GeoIP page as `Local GeoIP`.
- Removed the historical Review Analytics tables from the active fresh-install `database/schema.sql`; historical migration/changelog documents remain as archive history and do not re-enable the feature.
