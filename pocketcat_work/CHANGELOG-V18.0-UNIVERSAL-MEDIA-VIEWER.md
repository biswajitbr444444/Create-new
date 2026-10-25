# V18.0 — Universal Media Viewer & Player

- Media Library → Replaced the basic preview overlay with a responsive full-screen universal viewer for images, video and audio, while retaining existing media management actions.
- Image viewer → Added wheel zoom, drag-to-pan, zoom reset/in/out, 90-degree rotation, fullscreen and fit/stretch/aspect controls.
- Video/audio playback → Added custom transport controls, seek/buffer indication, volume/mute, speed selection, looping, A–B repeat, night mode, UI lock, fullscreen and Picture-in-Picture where supported.
- HLS → Added native HLS preference and lazy-loaded hls.js fallback, manifest-driven quality options and a 480p-capped Data Saver mode.
- Audio → Added a five-band Web Audio equalizer with Normal, Bass Boost, Vocal and Classical presets, plus track selection when exposed by the browser/stream.
- Subtitles → Added embedded track selection, local VTT/SRT loading and subtitle timing adjustment controls.
- Smart resume → Added local playback progress and resume/start-over prompt; resume keys are hashed instead of storing raw media URLs as localStorage keys.
- Public file shares → Added Preview buttons opening the same viewer using the existing HMAC-scoped tokenized share asset endpoint, and a secure endpoint download action only when downloads are allowed.
- Public streaming → Retained the existing token validation and byte-range streaming endpoint for seekable media; no new filesystem or cloud paths are emitted to the viewer UI.
- Lifecycle → Added cleanup for media sources, HLS instances, Web Audio contexts, object URLs, modal state and page scroll locking.

## Verification

- PHP syntax checked for the modified Media Library and public share page.
- JavaScript syntax checked with Node.js.
- Archive/package and integration smoke checks performed locally; browser/device playback and live-server authorization behavior still require deployment-environment verification with actual media files and share permissions.
