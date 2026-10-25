# PocketCat V18.8 — Bulk Selection, Multi-Format Downloads & Quick Delete

## Changelog
- **File Storage & Sharing selection bar** → The old ZIP-only control did not expose the requested download modes → Added original-file, single-archive and separate-archive modes, ten format choices, cancel-selection, live selected count and an accessible status region.
- **Visible selection** → Select-all could include filtered-out cards → Select All now applies to visible cards and reflects checked/indeterminate states; Cancel Selection clears all checks.
- **Archive downloads** → Dashboard hard-coded ZIP → Dashboard sends selected IDs, mode and format to the existing server archive endpoint; archive response remains a streamed HTTP attachment rather than requiring a UI-only archive implementation.
- **Quick Bulk Delete** → Bulk deletion stopped at the first storage error and did not report per-item outcomes → Deletion now checks ownership in the query, processes each authorized item independently, returns deleted IDs and individual safe failure messages, and logs internal details server-side only.
- **Deletion confirmation** → Generic delete label did not clearly communicate impact → Renamed to Quick Delete and added an explicit irreversible-deletion confirmation.
- **Responsive action controls** → Dense controls were awkward on mobile → Added wrapping, sticky contextual actions, mobile sizing and live progress/status text.

## Important behavior notes
- Original-format mode triggers separate downloads to preserve each original filename and extension. Browser-managed individual downloads cannot preserve directory hierarchy; choose an archive mode when the directory hierarchy must be retained.
- The current folder feature in this codebase is an organizational folder-marker model. It does not represent a full virtual filesystem with recursive folder ownership semantics, so Quick Delete currently deletes selected file records, not folder trees.
- `.7z` and `.rar` creation depends on the corresponding server command-line utilities being installed. Unsupported server capabilities return a user-facing error rather than silently producing the wrong format.
- Archive generation stages file bytes in a private temporary directory and invokes server-side archive tools; PHP does not build a giant in-memory archive. Ensure the temporary filesystem has enough free space and a cleanup job for interrupted requests.
