# PocketCat V19.66 — Media Library UX and Upload Reliability

- Added a selectable 2/3/4 concurrent-upload control (default 2) to the resumable media uploader.
- Upload completion now uses item-specific toast messaging and automatically dismisses completed queue cards while retaining live media insertion.
- Improved media action menus with single-open behavior, click-outside dismissal, viewport-aware upward flipping, internal scrolling, and higher stacking order.
- Added reusable accessible confirmation modal and toast primitives to the media library and migrated confirmation forms away from native browser confirmation dialogs.
- Added full-screen controls for images in public multi-file share cards; the universal viewer continues to handle image zoom and media-specific controls.
- Bumped media-library asset cache versions to V19.66.

## Verification notes
PHP syntax checks and JavaScript syntax checks should be run in the deployment environment. Large upload ceilings remain constrained by host storage, PHP/web-server limits, and available disk space; the resumable chunk endpoint remains the transfer path and does not imply that every host can accept 10TB files.
