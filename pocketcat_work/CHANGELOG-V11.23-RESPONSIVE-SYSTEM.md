# PocketCat V11.23 — Responsive System

## Goal
Replace the previous breakpoint patch with one fluid responsive system that adapts without desktop/mobile CSS fighting each other.

## Layout rules
- CSS viewport breakpoints: 1100px, 860px, 600px.
- Desktop-site mode around 980px remains a coherent desktop/tablet layout instead of being forced into a clipped phone layout.
- Grid/flex children use `min-width:0` so text, URLs and controls cannot expand their parent unexpectedly.
- App sidebar becomes an overlay only at 860px and below.
- Public navigation becomes the drawer only at 860px and below.
- Home hero uses two columns only when the viewport has enough room; it becomes a single fluid column before clipping can occur.
- Tables remain horizontally scrollable inside their own containers instead of widening the page.
- Forms, toolbars, action rows and cards wrap instead of overlapping.
- Safe-area padding is respected on phones.

## Cache/versioning
`app.css`, JS and the responsive stylesheet are served with version `11.23` so older InfinityFree/browser caches do not keep the previous breakpoint rules.

## Standalone pages
Responsive hardening was also applied to the standalone code viewer, file viewer, Code Governance page and file QR page.

## Validation
- PHP syntax checked across the complete project.
- JavaScript syntax checked across project JS files.
- ZIP contents verified after packaging.
