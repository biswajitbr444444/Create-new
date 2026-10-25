# PocketCat v19.70.1 — Pricing CTA Contrast Fix

## Fix
- Fixed low-contrast text on pricing, homepage, and billing plan-card action links (including “View plan” and “Request upgrade”).
- Ensured primary blue buttons use white text across normal, visited, hover, focus, and active states.
- Preserved the Free plan's secondary white button with dark blue text.
- Added a visible keyboard focus outline for plan-card action links.
- Bumped the `app.css` cache key so browsers fetch the updated stylesheet.

## Validation
- PHP syntax check passed for the modified `includes/functions.php`.
- Archive integrity check passed.
