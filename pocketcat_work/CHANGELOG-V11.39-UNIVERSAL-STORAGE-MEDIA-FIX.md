# PocketCat V11.39 — Universal Storage & Media Upload Reliability

## Fixed
- Fixed the Media Library upload INSERT statement that had a placeholder/value mismatch and caused the screenshot's **“Media operation failed safely”** error.
- Fixed the same placeholder mismatch in the Media API upload path.
- Removed the forced `webkitdirectory` behavior from normal file pickers. Admin and User Media now have separate **Choose files** and **Choose folder** controls.
- Added folder-aware drag/drop traversal for browsers that expose the File System Entry API.
- Added responsive picker/queue styles and a global `[hidden]` rule so dynamic storage-provider fields cannot remain visible because of `.form-card` display rules.
- Made **InfinityFree Local** the default storage driver for new installs and safe fallback resolution.
- Fixed storage-driver resolution so a configured S3 credential set cannot silently override an explicitly selected Local/Google Drive/OneDrive/Dropbox driver.
- Added per-user storage preferences and a personal Storage page at `/settings/files/` for non-admin users.
- Existing File Storage objects continue using the driver recorded on their own database row.
- Added automatic initialization of application `storage/tmp`, `uploads`, `.chunks`, media, support, QR, and file directories.
- Added a storage migration utility at `/admin/storage-migration/` and `/api/storage-migrate.php` for Local ↔ S3/R2, with source deletion only after successful migration.
- Added a `target_folder` upload-session field so Media Library folder names and dropped folder paths are persisted into physical storage paths.
- Improved upload request diagnostics and structured JSON failures for chunked uploads.

## Validation
- PHP syntax checked across the complete package.
- JavaScript syntax checked for the link UI and updated inline upload scripts are browser-compatible ES2018+.
- ZIP integrity checked before delivery.
