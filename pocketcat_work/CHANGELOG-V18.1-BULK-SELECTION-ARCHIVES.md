# PocketCat V18.1 — Bulk Selection & Multi-Format Downloads

## Changes

- **Public shared download page** → single-file-only interactions → added per-file selection, select-all, deselect-all, selected count, archive mode and format selectors.
- **Public share authorization** → bulk archive requests could use arbitrary IDs → archive requests now require every selected ID to belong to the requested share; view-only, expired, password-locked and download-limited shares are rejected.
- **Archive generation** → previous ZIP implementation read entire file contents into PHP memory → archive staging is disk-backed, local files are copied from storage paths, and archive output is streamed to the response in chunks.
- **Archive options** → ZIP-only action → supports ZIP, TAR, TAR.GZ, TAR.BZ2, TAR.XZ, GZ, BZ2, XZ, 7z and RAR selections; 7z/RAR require their respective server utilities, and ZIP-based modes require PHP ZipArchive.
- **Separate archive mode** → no multi-item archive mode → each selected item is archived individually and the resulting archives are bundled into a ZIP download.
- **Media Library selection** → no selected-item count or bulk archive controls → added a live selected count, archive mode/format controls and bulk archive action for media assets linked to owned file records.
- **Quick Bulk Delete** → generic destructive action → renamed to Quick Delete and added a count-aware confirmation with an explicit irreversibility warning; existing ownership-scoped database deletion remains in place.
- **Request safeguards** → unbounded bulk selections → bulk archive requests are limited to 500 selected file records and reject missing, non-owned or out-of-share selections.

## Deployment notes

- PHP `ZipArchive` is required for `.zip` and the separate-archives bundle.
- `tar`, `gzip`, `bzip2`, and `xz` should be installed for the corresponding formats. `7z` and `rar` are optional system utilities; requests fail with a user-facing error when unavailable.
- Large archive jobs currently run synchronously and stage files on the server's temporary filesystem. For very large selections or slow cloud providers, deploy a background worker/queue and configure a dedicated temporary-storage quota before enabling high concurrency.
- Original-format mode on the public share page initiates individual browser downloads. Browsers may require permission for multiple downloads; directory hierarchy is preserved by archive modes.
