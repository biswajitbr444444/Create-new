# PocketCat V15.5 — Admin View Media Library Access

## Fix
The user Media Library is now available to an administrator while Admin View is active.

- `includes/sidebar.php`: Media Library is no longer hidden for administrators in Admin View.
- `media/index.php`: removed the Admin View 403 guard so `/media/` works for the logged-in administrator as well.
- Admin CMS media management at `/admin/media/` remains separate.
- User-owned media data remains scoped to the currently authenticated account.

## Result
Admin View sidebar now includes:

- File Storage & Sharing
- Media Library
- Code Editor

The Admin View administration section remains unchanged.
