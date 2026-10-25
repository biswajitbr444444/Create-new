# PocketCat CMS Overhaul — Phase 2 Deployment

1. Back up the current PocketCat files and MySQL database.
2. Extract the package locally.
3. Upload/merge its contents into `pocketcat.online/htdocs`.
4. Do not overwrite the existing `storage/app-config.php`, `storage/app-secret.php`, or `storage/installed.lock`.
5. Open `/admin/posts/` and `/admin/pages/` after login.
6. Open one existing item and test Visual/Code mode, Featured Image, Revisions and Save.
7. Test Quick Edit and Bulk Edit from the listing.
8. Test `/posts/?slug=...`, `/pages/?slug=...`, category archives and comment moderation.
9. If an existing database is used, `ensure_cms_schema()` applies the CMS migrations when an authenticated CMS page is opened.
10. For a fresh database, use `database/schema.sql`; the Phase 2 schema creates `cms_categories` before dependent CMS tables.

## Hosting considerations
- Public comments are stored with a SHA-256 IP hash rather than a raw IP address.
- Public comment submission requires the existing PocketCat CSRF token.
- Media Library featured-image selection uses existing `media_assets` records.
- Public page/post content is intended for trusted administrator-authored HTML.
