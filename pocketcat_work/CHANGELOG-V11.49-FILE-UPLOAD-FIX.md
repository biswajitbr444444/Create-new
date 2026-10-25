# PocketCat V11.49 — Media Upload Reliability Fix

## Fixed
- Corrected the effective PHP upload request-limit calculation to use the lower of `upload_max_filesize` and `post_max_size`.
- Reduced resumable media chunks to a conservative 256 KiB maximum with multipart overhead headroom for shared hosting.
- Added same-origin credentials and JSON accept headers to resumable upload requests.
- Added progress/no-progress protection so a stalled chunk cannot loop forever.
- Aborts an active upload session when a chunk/finalize request fails, preventing orphaned temporary upload files.
- Media Library now preserves successful uploads when another selected file fails instead of immediately reloading and hiding the result.
- Upload queue now clearly marks successful and failed files so the user can see what actually happened.

## Compatibility
- Existing `upload_sessions` and media storage structure are preserved.
- No external upload service is used.
- Works with normal PHP uploads and shared-hosting request limits.
