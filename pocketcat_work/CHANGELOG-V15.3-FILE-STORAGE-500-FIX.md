# PocketCat V15.3 — File Storage HTTP 500 Fix

- Fixed `/files/` HTTP 500 on MySQL installations using `ONLY_FULL_GROUP_BY`.
- Replaced the invalid outer `GROUP BY f.id` query with a grouped derived share-link lookup that returns one link per file.
- Preserves existing File Storage & Sharing UI, share links, analytics, downloads, uploads, and V15 testing-mode behavior.
