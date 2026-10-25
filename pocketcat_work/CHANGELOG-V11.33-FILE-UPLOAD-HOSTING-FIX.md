# PocketCat V11.33 — Shared Hosting Upload Fix

- Replaced raw `application/octet-stream` chunk requests with standard multipart/form-data chunks.
- This avoids shared-hosting/ModSecurity rules that can return HTTP 500 for raw binary API bodies or custom upload headers.
- Each local fallback chunk remains 128–512 KB and is therefore well below the detected hosting POST/upload limit.
- The server now receives each chunk through `$_FILES`, validates its upload error, appends it to a temporary file, and finalizes only after the complete file is received.
- Added explicit storage-directory checks and clearer offset/chunk errors.
- Existing S3/R2 and connected-cloud upload flows remain supported.
