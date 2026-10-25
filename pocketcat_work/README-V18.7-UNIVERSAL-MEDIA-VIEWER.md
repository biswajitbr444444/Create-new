# PocketCat V18.7 — Universal Media Viewer

This update integrates the shared Universal Media Viewer into File Storage & Sharing, Media Library, Admin Media Library, and public share pages. It keeps the existing PHP application, upload/storage APIs, and share-token model intact.

## Deployment

1. Back up the current deployment.
2. Upload the contents of this archive to the existing PocketCat application root, preserving directory structure.
3. Ensure the web server can serve the updated `assets/js` and `assets/css` files.
4. Verify a local image, browser-supported video, and audio file in File Storage & Sharing and Media Library.
5. Verify a public share preview and confirm that view-only links do not display a download action.
6. Test range seeking against `/file/share/asset.php` in staging.

No database migration is required. Remote storage provider streaming remains disabled in the public asset endpoint until a secure provider-specific proxy implementation is added.
