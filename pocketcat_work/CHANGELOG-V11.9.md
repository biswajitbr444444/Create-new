# PocketCat V11.9 — QR Suite + Redirect UX

## QR editor
- Responsive desktop/mobile QR editor with compact controls and live preview.
- Functional foreground/background color controls.
- Auto QR sizing based on available preview width, plus fixed resolutions.
- Live margin, logo, image position, scale and opacity controls.
- Background and foreground image upload (PNG/JPG/WEBP/GIF, max 3 MB each).
- Saved QR designs per link in `qr_designs` with automatic runtime schema migration.
- PNG and SVG download.
- Native Web Share plus WhatsApp, Telegram, X and Facebook share actions.
- New links open the QR editor automatically after creation.
- Links list has direct `QR Edit` access.

## Redirect transition
- Removed the manual `Continue` link from intermediate redirect flows.
- New Admin → Site settings toggle: `Show PocketCat brand briefly during intermediate redirects`.
- Default is **Off**.
- When enabled, branded intermediate transition lasts about 450 ms before redirecting.
- Pixel/deep-link intermediate flows retain their required behavior without exposing a manual Continue action.

## Mobile polish
- QR preview and controls collapse to one column on small screens.
- URL/action overflow remains horizontally scrollable instead of breaking the page.
