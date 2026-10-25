# PocketCat V19.65 — File Storage & Media UX Fixes

- **Media preview** → image files could inherit media-player controls → image previews now expose image-specific zoom/rotate/fullscreen controls while hiding playback-only controls and timeline.
- **Public share page** → redundant QR Code action in public file view → removed the QR Code button while retaining preview and download actions.
- **File actions** → copying a link required using Share or another route → added a direct Copy Link action for existing and newly uploaded file cards with clipboard fallback.
- **Color pickers** → multiple picker popovers could remain open and could be cut off → added single-active picker behavior, outside-click dismissal, viewport flipping and internal scrolling.
- **Media Library preferences** → browser-native alert/confirm dialogs interrupted workflows → replaced preference validation notices and reset confirmation with in-app UI.
- **File selection / mobile UI** → improved responsive checkbox alignment and filename wrapping without removing existing layout behavior.
- **Preservation** → retained the existing resumable/chunked upload pipeline, configurable 2/3/4 upload concurrency, soft-delete Trash workflow, custom file dialogs and streamed archive download endpoint.

## Verification

Static PHP and JavaScript syntax checks were run against modified files. Full integration testing requires a deployed instance with its configured database, storage driver, web server limits, and optional archive utilities.
