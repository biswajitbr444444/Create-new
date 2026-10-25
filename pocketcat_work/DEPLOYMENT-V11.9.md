# PocketCat V11.9 Deployment

1. Back up the current `pocketcat.online/htdocs` files.
2. Extract this package and upload/merge its contents into `pocketcat.online/htdocs`.
3. **Do not overwrite** `storage/app-config.php`, `storage/app-secret.php`, or `storage/installed.lock` on an already-installed site.
4. Keep the existing database credentials/runtime config.
5. Open `/links/` and test: create link → QR editor opens automatically.
6. Test `/qr/<slug>/`: color controls, auto-size, image upload, save, PNG/SVG download and share buttons.
7. Test Admin → Site settings → Short-link redirect transition. It is **Off by default**.
8. If enabling the branded transition, PocketCat appears for about 450 ms and there is no manual Continue link.

The runtime creates `qr_designs` automatically after installation. The SQL schema also includes the table for fresh installations.
