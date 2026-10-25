# PocketCat V11.32 — File Upload Reliability Fix

- Fixed local/InfinityFree file uploads by switching the local fallback to sequential raw-binary chunk uploads instead of one large multipart `$_FILES` request.
- Added clear PHP upload error handling for size, partial, temp-directory, write, and extension failures.
- Added MIME detection fallback when the PHP Fileinfo extension is unavailable.
- Added writable-directory checks and `copy()` fallback after `move_uploaded_file()`.
- Added precise frontend HTTP/JSON error messages instead of generic “Server error”.
- Small and large local files now use the same 512 KB chunk transport, keeping each request comfortably below common shared-hosting POST limits.
- Existing S3/R2 presigned and connected-cloud upload flows remain supported.
