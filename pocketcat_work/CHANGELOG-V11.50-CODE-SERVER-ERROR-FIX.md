# PocketCat V11.50 — Code Server Error Fix

- Fixed Code Editor publish/save failures caused by missing `mbstring` by using a safe `mb_strlen` fallback.
- Hardened code schema initialization so legacy installations repair additive code columns without one failed DDL statement blocking all code tables.
- Added safer API error handling and actionable database/network error messages instead of an opaque `server_error` toast.
- Code Editor requests now explicitly use same-origin credentials.
- Saving/publishing an existing snippet now preserves/sets `published_at` correctly.
- Cache-busted Code Editor assets to V11.50.
