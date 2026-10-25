# PocketCat V18.2 — Media Library previews and display preferences

- Added persistent per-surface Media Library preferences using namespaced browser localStorage for user and admin views.
- Added activity, alphabetical A–Z/Z–A, largest-first and smallest-first sorting; pinned items stay above unpinned items.
- Added persistent Grid/List preference and a reset action to restore default display settings.
- Added a 24-color folder accent palette and separate 24-color border palette for selected media items.
- Added file-type-aware icons for APK, PDF, archives, Office documents, text/code, audio and generic files.
- Added best-effort client-side video frame thumbnail capture with a central play control; video remains playable through the control.
- Added size/MIME metadata to media cards and responsive styling for mobile and desktop.

## Notes

- Preferences are per browser/device and per Media Library surface. They are not synchronized across devices; user-database synchronization would require a preference API and schema change.
- Video thumbnail capture is best-effort and depends on browser codec support and same-origin/CORS access. Unsupported video formats retain the native player preview.
- Folder accents apply to items belonging to a folder. Border colors apply to the currently selected media items.
