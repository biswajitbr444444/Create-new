# PocketCat V18.7 — Universal Media Viewer & Player

## Changelog

- **Universal Media Viewer →** Previously available viewer code was not loaded on the main File Storage & Sharing page and media-library pages → Added shared viewer CSS/JS integration to File Storage & Sharing, Media Library, Admin Media Library, and the public share landing page.
- **File Storage & Sharing media cards →** Image, video, and audio items had no in-page universal preview action → Added a Preview control that opens the shared viewer using the existing application media asset endpoint.
- **Media Library Preview / Open in Files →** Preview behavior could fall back to a separate legacy modal or navigate away → Routed supported media previews through the universal viewer while preserving other file actions.
- **Public share downloads →** View-only shares could inherit the preview URL as a download target → The public viewer now only displays a download action when the server-rendered share permissions provide a dedicated tokenized download endpoint.
- **Audio navigation →** Audio playback had no previous/next navigation → Added previous/next controls that navigate among supported media preview items on the current page.
- **Viewer interaction →** Backdrop dismissal did not work consistently and UI Lock could leave users unable to restore controls → Backdrop close and tap-to-unlock behavior are now supported.
- **Playback behavior →** Opening a media item could start audio/video unexpectedly and HLS initialization opened the quality panel automatically → Playback starts from the explicit Play control, and quality settings remain user-invoked.
- **Subtitle synchronization →** The sync offset was displayed but not applied to loaded external cues → External VTT/SRT cues now receive the selected offset when loaded and adjusted.
- **Aspect ratio and accessibility →** Viewer state could carry over between files and focus was not restored on close → Reset stage ratio between sessions, add dialog focusability, and restore focus to the previously active element.
- **Public streaming →** Existing public asset endpoint already used token/item validation and byte-range responses → Preserved the endpoint and its `Accept-Ranges: bytes` / `Content-Range` behavior; no direct storage-provider URLs are inserted by the viewer.

## Notes / browser limitations

- Codec support depends on the visitor's browser and device. MKV and some audio formats are not universally playable through HTML5.
- HLS variant selection is available when an HLS manifest is used and hls.js is supported; native HLS browsers retain native playback behavior.
- Browser APIs do not expose all embedded audio/subtitle tracks for every container/browser combination. The viewer exposes tracks that the browser or HLS engine reports.
- The current public asset proxy supports local storage. The existing endpoint intentionally returns a generic unavailable message for non-local storage drivers rather than leaking provider URLs. Remote-provider secure proxy streaming needs a provider-specific backend streaming implementation before it can be enabled.
