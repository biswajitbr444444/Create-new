# PocketCat V11.36 — User Media Library parity + tenancy

- Rebuilt `/media/` to use the same responsive media-management workspace as `/admin/media/`.
- Added overview stats, search/type/folder/date/sort filters, folder chips and Grid/List view.
- Added multi-file media upload, `webkitdirectory` folder selection, public URL import, previews, favorites, edit details, copy URL, bulk move/delete and folder create/rename/delete.
- Added `user_id` tenancy column/indexes to `media_assets`; user-facing reads and writes are always constrained by the authenticated user ID.
- User uploads are stored under `uploads/media/{user_id}/` to keep physical storage separated by tenant.
- Added `/api/media/` JSON endpoint with the same user-scoped folder, listing, URL, upload, edit, favorite and bulk actions.
- Added date filtering to the admin media view as well so both interfaces expose the same filter model.
- Legacy media remains nullable/global for backward compatibility; it is not exposed through `/media/` unless it belongs to the authenticated user.
