# PocketCat V11.24 — Link / QR Functional + Responsive Fix

## Fixed from reported screenshots
- Link History QR controls are real `<button>` controls, not text-only links.
- Show / Hide QR state is managed with `aria-expanded` and the QR panel's `hidden` state.
- QR codes are generated per short-link card and remain responsive.
- Copy buttons use Clipboard API with a secure fallback.
- Native device sharing uses `navigator.share()` when available.
- WhatsApp and Facebook direct share actions are wired; Instagram copies the link and opens Instagram because Instagram does not provide a general web URL-share endpoint.
- Edit Link API bootstrap path was corrected from `../../bootstrap.php` to `../bootstrap.php`.
- Edit modal now has a server-side POST fallback if the AJAX endpoint cannot be reached.
- Link cards include Edit, Customize QR, Share, Analytics and Delete actions.
- Added a dedicated My QR Code section and a complete History section.
- CMS taxonomy/search forms were hardened against horizontal overflow on narrow screens.
- Global responsive rules force grid children to `min-width:0`, prevent media/text overflow, and stack multi-column layouts on small screens.
- CSS/JS cache version bumped to 11.24.

## Deployment
No database migration is required for these UI/action changes. Existing `qr_designs` support remains compatible.
