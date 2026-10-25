# V14 — Media Library ↔ File Storage unified sync

- `file_objects` is the canonical file record for uploaded Media Library assets.
- `media_assets.canonical_file_id` links the catalog row to that canonical record.
- New Media uploads create the canonical file and media row in one transaction.
- `/media/` links to `/files/?highlight={file_id}`.
- `/files/` exposes stable `#file-{file_id}` anchors and blue deep-link highlighting.
- File Storage deletion removes its linked Media Library row.
- Legacy local Media uploads are backfilled by the V14 SQL migration.
