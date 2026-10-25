# PocketCat V11.30 — Site Settings Responsive Fix

## Fixed
- Fixed a CSS class collision where the public homepage decorative `.check` class overrode real form checkbox styling.
- Site Settings checkbox label now uses a full-width responsive flex layout and cannot collapse to a 17px-wide column.
- Long redirect-transition help text wraps safely instead of running underneath the Save button.
- Save Site Settings is placed in a dedicated action area with predictable spacing.
- Mobile Save and Reset controls become full-width stacked buttons.
- Preserved existing Site Settings POST/CSRF/reset behavior.
