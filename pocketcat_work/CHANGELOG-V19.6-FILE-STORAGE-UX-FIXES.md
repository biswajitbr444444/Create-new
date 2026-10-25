# PocketCat V19.6 — File Storage & Sharing Fixes

## Applied changes

- **File downloads and image previews** → local file endpoint always forced attachment behavior and image thumbnails used the HTML share page → serve local content inline for preview URLs and as attachments only for explicit downloads; cloud assets use the asset URL for previews. Set MIME, length, `Content-Disposition`, and `nosniff` headers.
- **Bulk Quick Delete** → bulk deletion physically removed storage objects and bypassed Trash → change the bulk endpoint to soft-delete by setting `trashed_at`/`trashed_by`, sync the media catalog tombstone, and record audit events.
- **Selection on touch devices** → cards lacked long-press selection → add a 500 ms touch long-press handler and visible selected styling.
- **Select all visible** → ensure selection applies to every visible rendered card and updates indeterminate state.
- **Menus and color palettes** → menus could remain open or clip at viewport edges → outside-click closing, single-open menu behavior, vertical flip, scroll-limited menus and palette grids.
- **Native browser dialogs** → file info/delete/download and Trash actions used blocking browser dialogs → use accessible custom modal confirmation/info surfaces.
- **Trash management** → no bulk selection/restore/delete controls → add select all, cancel selection, bulk restore, and bulk permanent deletion with per-item error reporting.
- **Upload concurrency** → parallel uploads were fixed at three → add a user selector for 2/3/4 concurrent uploads with a default of two.
- **Upload completion** → progress cards remained after successful uploads and image thumbnails could point to HTML → update the live list with binary image asset URLs and auto-dismiss successful queue cards.
- **Public file page** → small files could display as 0.0 MB, QR action cluttered the page, and images lacked a full-screen option → dynamically format byte units, remove QR action, and add fullscreen image preview.
- **Responsive polish** → long names and mobile menus could overflow → strengthen card wrapping, menu scrolling, and mobile toolbar sizing.

## Verification

PHP and JavaScript syntax checks and ZIP integrity checks are run for this package. End-to-end authentication, database mutation, storage provider behavior, and real-device browser interactions require deployment/staging credentials and are not claimed as completed here. Advanced folder navigation, folder move/copy conflict resolution, archive extraction/browsing in user File Storage, and admin-configurable multi-terabyte upload limits remain outside this verified implementation and require additional backend/data-model work before the full request can be considered complete.
